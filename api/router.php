<?php
/**
 * API Router — handles all /api/* requests
 * Called from public/api.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../controllers/SessionController.php';

// ─── Helper: send JSON response ───────────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── Handle CORS preflight ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(['ok' => true]);
}

// ─── Parse route ──────────────────────────────────────────────────────────────
$path   = trim($_GET['route'] ?? '', '/');
$parts  = explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// ─── Auth check helper ────────────────────────────────────────────────────────
$sessionCtrl = new SessionController();

function requireAuth(): array {
    global $sessionCtrl;
    $user = $sessionCtrl->getCurrentUser();
    if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);
    return $user;
}

// ─── Route Dispatch ───────────────────────────────────────────────────────────

$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;
$action   = $parts[2] ?? null;

switch ($resource) {

    // ═══ AUTH ═════════════════════════════════════════════════════════════════
    case 'auth':
        require_once __DIR__ . '/../controllers/AuthController.php';
        $auth = new AuthController();

        if ($id === 'register' && $method === 'POST') {
            $result = $auth->registerUser($body);
            jsonResponse($result, $result['success'] ? 201 : 400);
        }
        if ($id === 'login' && $method === 'POST') {
            $result = $auth->loginUser($body['email'] ?? '', $body['password'] ?? '');
            jsonResponse($result, $result['success'] ? 200 : 401);
        }
        if ($id === 'restaurant-register' && $method === 'POST') {
            $result = $auth->registerRestaurant($body);
            jsonResponse($result, $result['success'] ? 201 : 400);
        }
        if ($id === 'restaurant-login' && $method === 'POST') {
            $result = $auth->loginRestaurant($body['email'] ?? '', $body['password'] ?? '');
            jsonResponse($result, $result['success'] ? 200 : 401);
        }
        if ($id === 'admin-login' && $method === 'POST') {
            $result = $auth->loginAdmin($body['email'] ?? '', $body['password'] ?? '');
            jsonResponse($result, $result['success'] ? 200 : 401);
        }
        if ($id === 'logout' && $method === 'POST') {
            $auth->logout();
        }
        jsonResponse(['error' => 'Not found'], 404);
        break;

    // ═══ ORDERS ═══════════════════════════════════════════════════════════════
    case 'order':
        require_once __DIR__ . '/../controllers/OrderController.php';
        $orderCtrl = new OrderController();

        // POST /order/place
        if ($id === 'place' && $method === 'POST') {
            $user   = requireAuth();
            $result = $orderCtrl->placeOrder($body, $user['id']);
            jsonResponse($result, $result['success'] ? 201 : 400);
        }

        // GET /order/{id}/status
        if ($id && $action === 'status' && $method === 'GET') {
            $result = $orderCtrl->getOrderStatus($id);
            jsonResponse($result, $result['success'] ? 200 : 404);
        }

        // PATCH /order/{id}/status
        if ($id && $action === 'status' && $method === 'PATCH') {
            $user   = requireAuth();
            $result = $orderCtrl->updateStatus($id, $body['status'] ?? '', $user['role']);
            jsonResponse($result, $result['success'] ? 200 : 400);
        }

        // GET /order/my — user's orders
        if ($id === 'my' && $method === 'GET') {
            $user   = requireAuth();
            $orders = $orderCtrl->getUserOrders($user['id']);
            jsonResponse(['success' => true, 'orders' => $orders]);
        }

        // GET /order/restaurant/{id}
        if ($id === 'restaurant' && $action && $method === 'GET') {
            $orders = $orderCtrl->getRestaurantOrders($action);
            jsonResponse(['success' => true, 'orders' => $orders]);
        }

        // GET /order/queue
        if ($id === 'queue' && $method === 'GET') {
            requireAuth();
            jsonResponse([
                'queue'  => $orderCtrl->getDeliveryQueue(),
                'length' => $orderCtrl->getDeliveryQueueLength(),
            ]);
        }

        // POST /order/assign — delivery agent pops queue
        if ($id === 'assign' && $method === 'POST') {
            $next = $orderCtrl->assignNextDelivery();
            jsonResponse(['success' => true, 'assignment' => $next]);
        }

        jsonResponse(['error' => 'Not found'], 404);
        break;

    // ═══ RESTAURANTS ══════════════════════════════════════════════════════════
    case 'restaurant':
        require_once __DIR__ . '/../controllers/RestaurantController.php';
        $restCtrl = new RestaurantController();

        // GET /restaurant — list all with filters
        if (!$id && $method === 'GET') {
            $filters = $_GET;
            unset($filters['route']);
            $list = $restCtrl->getAll($filters);
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');
            jsonResponse(['success' => true, 'restaurants' => $list]);
        }

        // GET /restaurant/{id}
        if ($id && !$action && $method === 'GET') {
            $r = $restCtrl->getById($id);
            if (!$r) jsonResponse(['error' => 'Not found'], 404);
            jsonResponse(['success' => true, 'restaurant' => $r]);
        }

        // GET /restaurant/{id}/menu
        if ($id && $action === 'menu' && $method === 'GET') {
            $menu = $restCtrl->getMenu($id);
            jsonResponse(['success' => true, 'menu' => $menu]);
        }

        // POST /restaurant/{id}/menu — add menu item
        if ($id && $action === 'menu' && $method === 'POST') {
            $user = requireAuth();
            $result = $restCtrl->addMenuItem($id, $body);
            jsonResponse($result, $result['success'] ? 201 : 400);
        }

        // DELETE /restaurant/{id}/menu/{item_id}
        if ($id && $action === 'menu' && isset($parts[3]) && $method === 'DELETE') {
            $user   = requireAuth();
            $result = $restCtrl->deleteMenuItem($id, $parts[3]);
            jsonResponse($result);
        }

        // PATCH /restaurant/{id}/menu/{item_id}
        if ($id && $action === 'menu' && isset($parts[3]) && $method === 'PATCH') {
            $user   = requireAuth();
            $result = $restCtrl->updateMenuItem($id, $parts[3], $body);
            jsonResponse($result);
        }

        // POST /restaurant/{id}/heartbeat
        if ($id && $action === 'heartbeat' && $method === 'POST') {
            $restCtrl->setOnline($id);
            jsonResponse(['success' => true, 'ttl' => RESTAURANT_ONLINE_TTL]);
        }

        // GET /restaurant/{id}/online
        if ($id && $action === 'online' && $method === 'GET') {
            jsonResponse(['online' => $restCtrl->isOnline($id)]);
        }

        // PATCH /restaurant/{id}
        if ($id && !$action && $method === 'PATCH') {
            $user = requireAuth();
            $ok   = $restCtrl->update($id, $body);
            jsonResponse(['success' => $ok]);
        }

        // POST /restaurant/{id}/upload-image — upload image for restaurant/menu item
        if ($id && $action === 'upload-image' && $method === 'POST') {
            requireAuth();

            if (empty($_FILES['image'])) {
                jsonResponse(['success' => false, 'error' => 'No image file received'], 400);
            }

            $file    = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed)) {
                jsonResponse(['success' => false, 'error' => 'Only JPG, PNG, WebP or GIF images allowed'], 400);
            }
            if ($file['size'] > $maxSize) {
                jsonResponse(['success' => false, 'error' => 'Image must be under 5 MB'], 400);
            }

            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg');
            $filename = 'rest_' . $id . '_' . uniqid() . '.' . $ext;
            $destDir  = __DIR__ . '/../public/uploads/';
            if (!is_dir($destDir)) mkdir($destDir, 0775, true);
            $dest = $destDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                jsonResponse(['success' => false, 'error' => 'Failed to save image'], 500);
            }

            $imageUrl = BASE_URL . '/uploads/' . $filename;
            jsonResponse(['success' => true, 'url' => $imageUrl, 'filename' => $filename]);
        }

        jsonResponse(['error' => 'Not found'], 404);
        break;

    // ═══ USER ═════════════════════════════════════════════════════════════════
    case 'user':
        require_once __DIR__ . '/../models/UserModel.php';
        $userModel = new UserModel();
        $user      = requireAuth();

        // GET /user/profile
        if ($id === 'profile' && $method === 'GET') {
            $profile = $userModel->findById($user['id']);
            unset($profile['password']);
            jsonResponse(['success' => true, 'user' => $profile]);
        }

        // PATCH /user/profile
        if ($id === 'profile' && $method === 'PATCH') {
            $ok = $userModel->update($user['id'], $body);
            jsonResponse(['success' => $ok]);
        }

        // POST /user/address
        if ($id === 'address' && $method === 'POST') {
            $ok = $userModel->addAddress($user['id'], $body);
            jsonResponse(['success' => $ok]);
        }

        // DELETE /user/address/{address_id}
        if ($id === 'address' && $action && $method === 'DELETE') {
            $ok = $userModel->removeAddress($user['id'], $action);
            jsonResponse(['success' => $ok]);
        }

        // POST /user/favorite/{restaurant_id}
        if ($id === 'favorite' && $action && $method === 'POST') {
            $ok = $userModel->addFavorite($user['id'], $action);
            jsonResponse(['success' => $ok]);
        }

        // DELETE /user/favorite/{restaurant_id}
        if ($id === 'favorite' && $action && $method === 'DELETE') {
            $ok = $userModel->removeFavorite($user['id'], $action);
            jsonResponse(['success' => $ok]);
        }

        jsonResponse(['error' => 'Not found'], 404);
        break;

    // ═══ REVIEWS ══════════════════════════════════════════════════════════════
    case 'review':
        require_once __DIR__ . '/../models/ReviewModel.php';
        require_once __DIR__ . '/../models/RestaurantModel.php';
        $reviewModel = new ReviewModel();
        $restModel   = new RestaurantModel();

        // POST /review
        if (!$id && $method === 'POST') {
            $user   = requireAuth();
            $body['user_id']   = $user['id'];
            $body['user_name'] = $user['name'];
            $reviewId = $reviewModel->create($body);
            if ($reviewId) {
                $restModel->addRating($body['restaurant_id'], (float)($body['rating'] ?? 0));
                // Bust review cache and restaurant doc cache (avg_rating changed)
                $redis = RedisClient::getInstance();
                if (RedisClient::isAvailable()) {
                    $redis->del('reviews:' . $body['restaurant_id']);
                    $redis->del('restaurant:doc:' . $body['restaurant_id']);
                    foreach ($redis->keys('restaurants:list:*') as $k) $redis->del($k);
                }
                jsonResponse(['success' => true, 'review_id' => $reviewId]);
            }
            jsonResponse(['success' => false, 'error' => 'Review failed or already submitted'], 400);
        }

        // GET /review/{restaurant_id}
        if ($id && $method === 'GET') {
            $redis = RedisClient::getInstance();
            if (RedisClient::isAvailable()) {
                $cacheKey = 'reviews:' . $id;
                $cached   = $redis->get($cacheKey);
                if ($cached !== null) {
                    jsonResponse(['success' => true, 'reviews' => json_decode($cached, true)]);
                }
                $reviews = $reviewModel->findByRestaurant($id);
                $redis->set($cacheKey, json_encode($reviews), CACHE_REVIEWS);
            } else {
                $reviews = $reviewModel->findByRestaurant($id);
            }
            jsonResponse(['success' => true, 'reviews' => $reviews]);
        }

        jsonResponse(['error' => 'Not found'], 404);
        break;

    // ═══ DASHBOARD ════════════════════════════════════════════════════════════
    case 'dashboard':
        require_once __DIR__ . '/../models/OrderModel.php';
        require_once __DIR__ . '/../models/RestaurantModel.php';
        require_once __DIR__ . '/../models/UserModel.php';
        require_once __DIR__ . '/../controllers/OrderController.php';
        require_once __DIR__ . '/../controllers/RestaurantController.php';

        // GET /dashboard/analytics
        if ($id === 'analytics' && $method === 'GET') {
            $redis = RedisClient::getInstance();
            $cacheKey = 'admin:analytics:cache';

            if (RedisClient::isAvailable()) {
                $cached = $redis->get($cacheKey);
                if ($cached) {
                    jsonResponse(['success' => true, 'analytics' => json_decode($cached, true)]);
                }
            }

            $orderModel   = new OrderModel();
            $restModel    = new RestaurantModel();
            $userModel    = new UserModel();
            $orderCtrl    = new OrderController();
            $restCtrl     = new RestaurantController();

            $analytics = [
                'total_users'         => $userModel->count(),
                'total_restaurants'   => $restModel->count(),
                'total_orders'        => $orderModel->count(),
                'orders_by_status'    => $orderModel->countByStatus(),
                'active_orders'       => $orderCtrl->getActiveOrdersFromRedis(),
                'delivery_queue_len'  => $orderCtrl->getDeliveryQueueLength(),
                'online_restaurants'  => $restCtrl->getOnlineCount(),
                'revenue_by_restaurant' => $restModel->aggregateRevenuePerRestaurant(),
                'most_ordered_items'  => $orderModel->aggregateMostOrderedItem(),
                'avg_delivery_time'   => $orderModel->aggregateAvgDeliveryTime(),
            ];

            if (RedisClient::isAvailable()) {
                $redis->set($cacheKey, json_encode($analytics), 60); // Cache for 60 seconds
            }

            jsonResponse(['success' => true, 'analytics' => $analytics]);
        }

        jsonResponse(['error' => 'Not found'], 404);
        break;

    default:
        jsonResponse(['error' => 'API endpoint not found'], 404);
}
