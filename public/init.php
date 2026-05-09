<?php
/**
 * Public entry point — routes all requests
 */

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/config.php';

// Determine page
$page = basename($_SERVER['PHP_SELF'], '.php');

// API requests are handled separately via api.php
