<?php
/**
 * Order Controller — Full order lifecycle management
 * Redis-first with MongoDB fallback
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../models/OrderModel.php';
require_once __DIR__ . '/../models/RestaurantModel.php';

class OrderController {
    private RedisClient     $redis;
    private OrderModel      $orderModel;
    private RestaurantModel $restaurantModel;

    public function __construct() {
        $this->redis           = RedisClient::getInstance();
        $this->orderModel      = new OrderModel();
        $this->restaurantModel = new RestaurantModel();
    }

    // ─── Place Order ──────────────────────────────────────────────────────────

    public function placeOrder(array $data, string $userId): array {
        // Rate limiting: 5 orders per hour per user
        if (!$this->checkRateLimit($userId)) {
            return ['success' => false, 'error' => 'Order limit reached. Maximum 5 orders per hour.'];
        }

        // Validate restaurant exists
        $restaurant = $this->restaurantModel->findById($data['restaurant_id']);
        if (!$restaurant) return ['success' => false, 'error' => 'Restaurant not found'];

        // Validate and enrich items
        $enrichedItems = $this->enrichItems($data['items'] ?? [], $restaurant);
        if (empty($enrichedItems)) return ['success' => false, 'error' => 'No valid items in cart'];

        // Calculate total
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $enrichedItems));
        if ($total < ($restaurant['min_order'] ?? 0)) {
            return ['success' => false, 'error' => "Minimum order is ₹{$restaurant['min_order']}"];
        }

        $orderData = [
            'user_id'          => $userId,
            'restaurant_id'    => $data['restaurant_id'],
            'items'            => $enrichedItems,
            'total_price'      => $total + ($restaurant['delivery_fee'] ?? 0),
            'delivery_address' => $data['delivery_address'] ?? [],
            'notes'            => $data['notes'] ?? '',
            'eta_minutes'      => 30,
        ];

        // Persist to MongoDB
        $orderId = $this->orderModel->create($orderData);
        if (!$orderId) return ['success' => false, 'error' => 'Failed to place order'];

        // Store in Redis for real-time tracking
        $this->storeOrderInRedis($orderId, $userId, $data['restaurant_id']);

        // Push to delivery queue
        $this->redis->rpush(DELIVERY_QUEUE_KEY, $orderId);

        // Increment rate limit counter
        $this->incrementRateLimit($userId);

        // Bust user orders cache so next load reflects new order
        $this->bustUserOrdersCache($userId);

        return [
            'success'  => true,
            'order_id' => $orderId,
            'total'    => $orderData['total_price'],
            'message'  => 'Order placed successfully!',
        ];
    }

    // ─── Get Order Status ─────────────────────────────────────────────────────

    public function getOrderStatus(string $orderId): array {
        // Check Redis first
        if (RedisClient::isAvailable()) {
            $redisData = $this->redis->hgetall("order:{$orderId}");
            if (!empty($redisData)) {
                // Normalize eta field
                if (empty($redisData['eta']) && isset($redisData['eta_minutes'])) {
                    $redisData['eta'] = $redisData['eta_minutes'] . ' mins';
                } elseif (empty($redisData['eta'])) {
                    $redisData['eta'] = '30 mins';
                }
                return ['success' => true, 'source' => 'redis', 'order' => $redisData];
            }
        }

        // Fallback to MongoDB
        $order = $this->orderModel->findById($orderId);
        if (!$order) return ['success' => false, 'error' => 'Order not found'];

        // Normalize eta field from MongoDB's eta_minutes integer
        if (empty($order['eta'])) {
            $order['eta'] = ($order['eta_minutes'] ?? 30) . ' mins';
        }

        return ['success' => true, 'source' => 'mongodb', 'order' => $order];
    }

    // ─── Update Order Status ──────────────────────────────────────────────────

    public function updateStatus(string $orderId, string $newStatus, string $actorRole): array {
        // Get current status
        $current = $this->getOrderStatus($orderId);
        if (!$current['success']) return $current;

        $currentStatus = $current['order']['status'] ?? '';

        // Validate transition
        $allowed = VALID_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowed)) {
            return ['success' => false, 'error' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'"];
        }

        // Update Redis
        if (RedisClient::isAvailable()) {
            $this->redis->hset("order:{$orderId}", 'status', $newStatus);
            $this->redis->hset("order:{$orderId}", 'updated_at', date('c'));
        }

        // Update MongoDB
        $this->orderModel->updateStatus($orderId, $newStatus);

        // Bust restaurant orders cache
        $this->bustRestaurantOrdersCache($current['order']['restaurant_id'] ?? '');
        $this->bustUserOrdersCache($current['order']['user_id'] ?? '');

        // If delivered → migrate from Redis to MongoDB + update computed fields
        if ($newStatus === STATUS_DELIVERED) {
            $this->onOrderDelivered($orderId);
        }

        // If out for delivery → remove from restaurant queue, push to delivery agent
        if ($newStatus === STATUS_OUT_FOR_DELIVERY) {
            $orderInfo = $this->orderModel->findById($orderId);
            if ($orderInfo) {
                $this->redis->rpush('agent:queue', $orderId);
            }
        }

        return ['success' => true, 'status' => $newStatus];
    }

    // ─── Order Delivered → Migration ──────────────────────────────────────────

    private function onOrderDelivered(string $orderId): void {
        $order = $this->orderModel->findById($orderId);
        if (!$order) return;

        // Update restaurant computed fields
        $this->restaurantModel->incrementOrderStats(
            $order['restaurant_id'],
            (float)$order['total_price']
        );

        // Remove from Redis
        $this->redis->del("order:{$orderId}");
    }

    // ─── Redis Order Storage ──────────────────────────────────────────────────

    private function storeOrderInRedis(string $orderId, string $userId, string $restaurantId): void {
        if (!RedisClient::isAvailable()) return;

        $this->redis->hmset("order:{$orderId}", [
            'status'        => STATUS_PLACED,
            'user_id'       => $userId,
            'restaurant_id' => $restaurantId,
            'eta'           => '30 mins',
            'placed_at'     => date('c'),
            'updated_at'    => date('c'),
        ]);
        // Orders expire after 24h if not cleaned
        $this->redis->expire("order:{$orderId}", 86400);
    }

    // ─── Rate Limiting ────────────────────────────────────────────────────────

    private function checkRateLimit(string $userId): bool {
        if (!RedisClient::isAvailable()) return true; // Skip if Redis down

        $key   = "rate:order:{$userId}";
        $count = (int)($this->redis->get($key) ?? 0);
        return $count < ORDER_RATE_LIMIT;
    }

    private function incrementRateLimit(string $userId): void {
        if (!RedisClient::isAvailable()) return;

        $key = "rate:order:{$userId}";
        $count = $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, RATE_WINDOW);
        }
    }

    // ─── Delivery Agent — Assign from Queue ───────────────────────────────────

    public function assignNextDelivery(): ?array {
        $orderId = $this->redis->lpop(DELIVERY_QUEUE_KEY);
        if (!$orderId) return null;

        $order = $this->orderModel->findById($orderId);
        return $order ? ['order_id' => $orderId, 'order' => $order] : null;
    }

    public function getDeliveryQueueLength(): int {
        return $this->redis->llen(DELIVERY_QUEUE_KEY);
    }

    public function getDeliveryQueue(): array {
        return $this->redis->lrange(DELIVERY_QUEUE_KEY, 0, -1);
    }

    // ─── Active Orders (Redis) ────────────────────────────────────────────────

    public function getActiveOrdersFromRedis(): array {
        $keys   = $this->redis->keys('order:*');
        $orders = [];
        foreach ($keys as $key) {
            $data = $this->redis->hgetall($key);
            if (!empty($data)) {
                $orderId       = str_replace('order:', '', $key);
                $data['id']    = $orderId;
                $orders[]      = $data;
            }
        }
        return $orders;
    }

    // ─── User Orders ──────────────────────────────────────────────────────────

    public function getUserOrders(string $userId, int $limit = 20): array {
        if (RedisClient::isAvailable()) {
            $cacheKey = "user:orders:{$userId}";
            $cached   = $this->redis->get($cacheKey);
            if ($cached !== null) return json_decode($cached, true);

            $result = $this->orderModel->findByUser($userId, $limit);
            // Cache for 30s — orders change frequently
            $this->redis->set($cacheKey, json_encode($result), 30);
            return $result;
        }
        return $this->orderModel->findByUser($userId, $limit);
    }

    public function bustUserOrdersCache(string $userId): void {
        if (RedisClient::isAvailable()) {
            $this->redis->del("user:orders:{$userId}");
        }
    }

    // ─── Restaurant Orders ────────────────────────────────────────────────────

    public function getRestaurantOrders(string $restaurantId): array {
        if (RedisClient::isAvailable()) {
            $cacheKey = "restaurant:orders:{$restaurantId}";
            $cached   = $this->redis->get($cacheKey);
            if ($cached !== null) return json_decode($cached, true);

            $result = $this->orderModel->findByRestaurant($restaurantId);
            $this->redis->set($cacheKey, json_encode($result), 30);
            return $result;
        }
        return $this->orderModel->findByRestaurant($restaurantId);
    }

    public function bustRestaurantOrdersCache(string $restaurantId): void {
        if (RedisClient::isAvailable()) {
            $this->redis->del("restaurant:orders:{$restaurantId}");
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function enrichItems(array $items, array $restaurant): array {
        $menuMap = [];
        foreach ($restaurant['menu_items'] ?? [] as $m) {
            $menuMap[$m['_id']] = $m;
        }

        $enriched = [];
        foreach ($items as $item) {
            $id  = $item['item_id'] ?? $item['_id'] ?? '';
            $qty = max(1, (int)($item['quantity'] ?? 1));
            if (isset($menuMap[$id]) && $menuMap[$id]['is_available']) {
                $enriched[] = [
                    'item_id'  => $id,
                    'name'     => $menuMap[$id]['name'],
                    'price'    => (float)$menuMap[$id]['price'],
                    'quantity' => $qty,
                    'type'     => $menuMap[$id]['type'] ?? 'veg',
                ];
            }
        }
        return $enriched;
    }
}
