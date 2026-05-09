<?php
/**
 * Application-wide constants and environment configuration
 */

// ─── MongoDB ─────────────────────────────────────────────────────────────────
define('MONGO_HOST', 'mongodb://127.0.0.1:27017');
define('MONGO_DB',   'food_delivery');

// ─── Redis ───────────────────────────────────────────────────────────────────
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_TTL',    86400);   // 24 hours
define('SESSION_PREFIX', 'session:');

// ─── Rate Limiting ───────────────────────────────────────────────────────────
define('ORDER_RATE_LIMIT', 5);    // max orders per hour
define('RATE_WINDOW',      3600); // 1 hour in seconds

// ─── Restaurant Heartbeat ────────────────────────────────────────────────────
define('RESTAURANT_ONLINE_TTL', 300); // 5 minutes

// ─── Order Status Constants ───────────────────────────────────────────────────
define('STATUS_PLACED',           'placed');
define('STATUS_ACCEPTED',         'accepted');
define('STATUS_PREPARING',        'preparing');
define('STATUS_OUT_FOR_DELIVERY', 'out_for_delivery');
define('STATUS_DELIVERED',        'delivered');
define('STATUS_CANCELLED',        'cancelled');

define('VALID_TRANSITIONS', [
    STATUS_PLACED           => [STATUS_ACCEPTED, STATUS_CANCELLED],
    STATUS_ACCEPTED         => [STATUS_PREPARING],
    STATUS_PREPARING        => [STATUS_OUT_FOR_DELIVERY],
    STATUS_OUT_FOR_DELIVERY => [STATUS_DELIVERED],
    STATUS_DELIVERED        => [],
    STATUS_CANCELLED        => [],
]);

// ─── Delivery Queue ───────────────────────────────────────────────────────────
define('DELIVERY_QUEUE_KEY', 'delivery:queue');

// ─── Cache TTLs ───────────────────────────────────────────────────────────────
define('CACHE_RESTAURANT_LIST', 300);  // 5 minutes — homepage restaurant list
define('CACHE_RESTAURANT_DOC',  300);  // 5 minutes — single restaurant doc
define('CACHE_REVIEWS',         120);  // 2 minutes — restaurant reviews

// ─── App ──────────────────────────────────────────────────────────────────────
define('APP_NAME',    'FoodRush');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/food_tracking_system/public');

// ─── Upload Paths ─────────────────────────────────────────────────────────────
define('UPLOAD_DIR',  __DIR__ . '/../public/uploads/');
define('UPLOAD_URL',  BASE_URL . '/uploads/');
