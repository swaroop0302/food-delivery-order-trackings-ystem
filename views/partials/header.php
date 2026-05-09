<?php
/* Shared navbar partial */
// Enable gzip for page responses
if (!ob_get_level()) {
    if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) &&
        str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') &&
        function_exists('ob_gzhandler')) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
}
if (!session_id()) session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/SessionController.php';
$sess = new SessionController();
$currentUser = $sess->getCurrentUser();
$role = $currentUser['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $pageTitle ?? APP_NAME ?> – <?= APP_NAME ?></title>
<meta name="description" content="<?= $pageDesc ?? 'Order food from the best restaurants near you' ?>">
<link rel="preload" href="/food_tracking_system/public/assets/css/style.css" as="style">
<link rel="preload" href="/food_tracking_system/public/assets/js/app.js" as="script">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap"></noscript>
<link rel="stylesheet" href="/food_tracking_system/public/assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'></text></svg>">
</head>
<body>
<nav class="navbar">
  <a class="brand" href="/food_tracking_system/public/index.php"> <?= APP_NAME ?></a>
  <div class="nav-search">
    <span class="icon"></span>
    <input type="text" id="globalSearch" placeholder="Search restaurants, dishes..." autocomplete="off">
  </div>
  <div class="nav-links" id="navLinks">
    <?php if (!$currentUser): ?>
      <a href="/food_tracking_system/public/login.php">Login</a>
      <a href="/food_tracking_system/public/register.php" class="btn btn-primary btn-sm">Sign Up</a>
    <?php elseif ($role === 'restaurant'): ?>
      <a href="/food_tracking_system/public/restaurant_dashboard.php">My Restaurant</a>
      <a href="/food_tracking_system/public/logout.php">Logout</a>
    <?php elseif ($role === 'admin'): ?>
      <a href="/food_tracking_system/public/admin.php">Admin</a>
      <a href="/food_tracking_system/public/logout.php">Logout</a>
    <?php else: ?>
      <a href="/food_tracking_system/public/profile.php"> <?= htmlspecialchars($currentUser['name']) ?></a>
      <a href="/food_tracking_system/public/orders.php">My Orders</a>
      <button class="cart-icon" id="cartBtn">
         Cart
        <span class="cart-badge" id="cartBadge" style="display:none">0</span>
      </button>
      <a href="/food_tracking_system/public/logout.php">Logout</a>
    <?php endif; ?>
  </div>
  <button class="nav-toggle" id="navToggle"></button>
</nav>

<!-- Cart Sidebar -->
<div class="cart-overlay" id="cartOverlay"></div>
<aside class="cart-sidebar" id="cartSidebar">
  <div class="cart-header">
    <h3> Your Cart</h3>
    <button class="cart-close" id="cartClose"></button>
  </div>
  <div class="cart-items" id="cartItems"></div>
  <div class="cart-footer" id="cartFooter"></div>
</aside>
