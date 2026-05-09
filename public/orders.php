<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'My Orders';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/controllers/OrderController.php';

$sess      = new SessionController();
$user      = $sess->requireLogin();
$orderCtrl = new OrderController();
$orders    = $orderCtrl->getUserOrders($user['id']);

require_once ROOT_DIR . '/views/partials/header.php';

$statusLabels = [
    'placed'           => 'Order Placed',
    'accepted'         => 'Accepted',
    'preparing'        => 'Preparing',
    'out_for_delivery' => 'On the way',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
];

function timeAgoPhp(string $iso): string {
    if (!$iso) return '';
    $diff = time() - strtotime($iso);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('d M Y', strtotime($iso));
}
?>
<main>
<div class="container" style="padding:2rem 0 4rem">
  <div class="page-header"><h1> My Orders</h1></div>

  <?php if (empty($orders)): ?>
    <div class="empty">
      <div class="empty-icon"></div>
      <h3>No orders yet</h3>
      <p>Start ordering from your favourite restaurants!</p>
      <a href="/food_tracking_system/public/index.php" class="btn btn-primary" style="margin-top:1rem">Browse Restaurants</a>
    </div>
  <?php else: ?>
    <div id="ordersList">
      <?php foreach ($orders as $o):
        $statusKey   = $o['status'] ?? 'placed';
        $statusText  = $statusLabels[$statusKey] ?? $statusKey;
        $itemCount   = count($o['items'] ?? []);
        $itemSummary = implode(' · ', array_map(
            fn($i) => htmlspecialchars($i['name']) . ' ×' . (int)$i['quantity'],
            $o['items'] ?? []
        ));
        $shortId = strtoupper(substr($o['_id'], -6));
        $ago     = timeAgoPhp($o['placed_at'] ?? '');
      ?>
      <div class="card card-body" style="margin-bottom:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:.75rem">
          <div>
            <div style="font-weight:700">Order #<?= $shortId ?></div>
            <div class="text-muted text-sm"><?= $ago ?> · <?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:.75rem">
            <span class="status-badge status-<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusText) ?></span>
            <strong style="color:var(--secondary)">₹<?= number_format((float)$o['total_price'], 2) ?></strong>
          </div>
        </div>
        <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem"><?= $itemSummary ?></div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
          <a href="/food_tracking_system/public/track.php?id=<?= htmlspecialchars($o['_id']) ?>" class="btn btn-outline btn-sm">Track Order</a>
          <?php if ($statusKey === 'delivered'): ?>
            <button class="btn btn-ghost btn-sm" onclick="reorder('<?= htmlspecialchars($o['_id']) ?>')">Reorder</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</main>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
