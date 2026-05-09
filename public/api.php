<?php
/**
 * API Entry Point — with gzip output compression
 * URL: /food_tracking_system/public/api.php?route=...
 */

// Enable gzip compression if client supports it
if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
    str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') &&
    function_exists('ob_gzhandler')) {
    ob_start('ob_gzhandler');
} else {
    ob_start();
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/api/router.php';
