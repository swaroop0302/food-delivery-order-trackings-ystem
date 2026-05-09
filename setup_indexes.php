<?php
/**
 * MongoDB Index Setup — run ONCE after installation
 * Usage: php setup_indexes.php
 *
 * Without these indexes MongoDB does full collection scans on every query.
 * With them, queries go from O(n) to O(log n).
 */

define('ROOT_DIR', __DIR__);
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/config/database.php';

$db = Database::getInstance()->getDB();
$created = [];

// ── restaurants ──────────────────────────────────────────────────────────────
$restaurants = $db->selectCollection('restaurants');

$restaurants->createIndex(['email' => 1], ['unique' => true, 'name' => 'email_unique']);
$created[] = 'restaurants.email (unique)';

$restaurants->createIndex(['is_active' => 1, 'avg_rating' => -1], ['name' => 'active_rating']);
$created[] = 'restaurants.is_active + avg_rating';

$restaurants->createIndex(['is_active' => 1, 'cuisine' => 1], ['name' => 'active_cuisine']);
$created[] = 'restaurants.is_active + cuisine';

$restaurants->createIndex(['is_active' => 1, 'is_veg' => 1], ['name' => 'active_veg']);
$created[] = 'restaurants.is_active + is_veg';

$restaurants->createIndex(['is_active' => 1, 'total_orders' => -1], ['name' => 'active_orders']);
$created[] = 'restaurants.is_active + total_orders';

// Text index for search on name + description
$restaurants->createIndex(
    ['name' => 'text', 'description' => 'text', 'cuisine' => 'text'],
    ['name' => 'text_search', 'weights' => ['name' => 10, 'cuisine' => 5, 'description' => 1]]
);
$created[] = 'restaurants.text (name + description + cuisine)';

// ── orders ────────────────────────────────────────────────────────────────────
$orders = $db->selectCollection('orders');

$orders->createIndex(['user_id' => 1, 'placed_at' => -1], ['name' => 'user_orders']);
$created[] = 'orders.user_id + placed_at';

$orders->createIndex(['restaurant_id' => 1, 'placed_at' => -1], ['name' => 'restaurant_orders']);
$created[] = 'orders.restaurant_id + placed_at';

$orders->createIndex(['status' => 1], ['name' => 'status']);
$created[] = 'orders.status';

$orders->createIndex(['status' => 1, 'delivered_at' => 1], ['name' => 'status_delivered']);
$created[] = 'orders.status + delivered_at';

// ── users ─────────────────────────────────────────────────────────────────────
$users = $db->selectCollection('users');

$users->createIndex(['email' => 1], ['unique' => true, 'name' => 'email_unique']);
$created[] = 'users.email (unique)';

// ── reviews ───────────────────────────────────────────────────────────────────
$reviews = $db->selectCollection('reviews');

$reviews->createIndex(['restaurant_id' => 1, 'created_at' => -1], ['name' => 'restaurant_reviews']);
$created[] = 'reviews.restaurant_id + created_at';

$reviews->createIndex(
    ['restaurant_id' => 1, 'user_id' => 1],
    ['unique' => true, 'name' => 'one_review_per_user']
);
$created[] = 'reviews.restaurant_id + user_id (unique — one review per user)';

// ── Done ─────────────────────────────────────────────────────────────────────
echo "\n MongoDB indexes created:\n\n";
foreach ($created as $idx) {
    echo "   • {$idx}\n";
}
echo "\n Done! Queries are now significantly faster.\n\n";
echo "Note: You can delete this file after running it.\n";
