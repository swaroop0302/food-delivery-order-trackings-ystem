<?php
/**
 * Restaurant Controller — Heartbeat, menu CRUD, analytics
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../models/RestaurantModel.php';

class RestaurantController {
    private RedisClient     $redis;
    private RestaurantModel $model;

    public function __construct() {
        $this->redis = RedisClient::getInstance();
        $this->model = new RestaurantModel();
    }

    // ─── Online Status / Heartbeat ────────────────────────────────────────────

    public function setOnline(string $restaurantId): void {
        $key = "restaurant:{$restaurantId}:online";
        $this->redis->set($key, '1', RESTAURANT_ONLINE_TTL);
    }

    public function isOnline(string $restaurantId): bool {
        $key = "restaurant:{$restaurantId}:online";
        return $this->redis->exists($key);
    }

    public function getOnlineRestaurants(): array {
        $keys = $this->redis->keys('restaurant:*:online');
        $ids  = [];
        foreach ($keys as $key) {
            preg_match('/restaurant:(.+):online/', $key, $m);
            if (isset($m[1])) $ids[] = $m[1];
        }
        return $ids;
    }

    public function getOnlineCount(): int {
        return count($this->getOnlineRestaurants());
    }

    // ─── Menu CRUD ────────────────────────────────────────────────────────────

    public function addMenuItem(string $restaurantId, array $item): array {
        $errors = $this->validateMenuItem($item);
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $itemId = $this->model->addMenuItem($restaurantId, $item);
        if (!$itemId) return ['success' => false, 'error' => 'Failed to add menu item'];

        $this->bustRestaurantCache($restaurantId);
        return ['success' => true, 'item_id' => $itemId];
    }

    public function updateMenuItem(string $restaurantId, string $itemId, array $fields): array {
        $ok = $this->model->updateMenuItem($restaurantId, $itemId, $fields);
        if ($ok) $this->bustRestaurantCache($restaurantId);
        return ['success' => $ok];
    }

    public function deleteMenuItem(string $restaurantId, string $itemId): array {
        $ok = $this->model->removeMenuItem($restaurantId, $itemId);
        if ($ok) $this->bustRestaurantCache($restaurantId);
        return ['success' => $ok];
    }

    public function getMenu(string $restaurantId): array {
        $restaurant = $this->model->findById($restaurantId);
        if (!$restaurant) return [];

        $items = $restaurant['menu_items'] ?? [];
        // Group by category
        $grouped = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? 'veg';
            $grouped[$type][] = $item;
        }
        return $grouped;
    }

    // ─── Restaurant Info ──────────────────────────────────────────────────────

    public function getAll(array $filters = []): array {
        if (RedisClient::isAvailable()) {
            $cacheKey = 'restaurants:list:' . md5(serialize($filters));
            $cached   = $this->redis->get($cacheKey);
            if ($cached !== null) return json_decode($cached, true);

            $result = $this->model->getAll($filters);
            $this->redis->set($cacheKey, json_encode($result), CACHE_RESTAURANT_LIST);
            return $result;
        }
        return $this->model->getAll($filters);
    }

    public function getById(string $id): ?array {
        if (RedisClient::isAvailable()) {
            $cacheKey = 'restaurant:doc:' . $id;
            $cached   = $this->redis->get($cacheKey);
            if ($cached !== null) return json_decode($cached, true);

            $result = $this->model->findById($id);
            if ($result) $this->redis->set($cacheKey, json_encode($result), CACHE_RESTAURANT_DOC);
            return $result;
        }
        return $this->model->findById($id);
    }

    public function update(string $id, array $fields): bool {
        $ok = $this->model->update($id, $fields);
        if ($ok) $this->bustRestaurantCache($id);
        return $ok;
    }

    /** Invalidate all caches for a restaurant (call after any mutation) */
    public function bustRestaurantCache(string $id): void {
        if (!RedisClient::isAvailable()) return;
        $this->redis->del('restaurant:doc:' . $id);
        // Bust all list caches — pattern delete
        foreach ($this->redis->keys('restaurants:list:*') as $key) {
            $this->redis->del($key);
        }
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    private function validateMenuItem(array $item): array {
        $errors = [];
        if (empty($item['name']))  $errors['name']  = 'Item name is required';
        if (!isset($item['price']) || $item['price'] <= 0) $errors['price'] = 'Valid price is required';
        if (!in_array($item['type'] ?? '', ['veg', 'non_veg', 'beverage'])) {
            $errors['type'] = 'Type must be veg, non_veg, or beverage';
        }
        if (($item['type'] ?? '') === 'non_veg' && !in_array($item['spice_level'] ?? '', ['low','medium','high'])) {
            $errors['spice_level'] = 'Spice level must be low, medium, or high';
        }
        return $errors;
    }
}
