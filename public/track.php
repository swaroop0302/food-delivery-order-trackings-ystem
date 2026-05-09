<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Track Order';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/controllers/OrderController.php';

$sess    = new SessionController();
$sess->requireLogin();
$orderId = $_GET['id'] ?? '';

$orderCtrl   = new OrderController();
$orderData   = $orderId ? $orderCtrl->getOrderStatus($orderId) : null;
$order       = $orderData['success'] ? $orderData['order'] : null;
$currentStatus = $order['status'] ?? 'placed';

$steps = [
    ['status' => 'placed',           'label' => 'Order Placed',     'desc' => 'Your order has been received',     'icon' => ''],
    ['status' => 'accepted',         'label' => 'Accepted',         'desc' => 'Restaurant confirmed your order',  'icon' => ''],
    ['status' => 'preparing',        'label' => 'Preparing',        'desc' => 'Chef is cooking your meal',        'icon' => '‍'],
    ['status' => 'out_for_delivery', 'label' => 'Out for Delivery', 'desc' => 'Rider is on the way to you',       'icon' => ''],
    ['status' => 'delivered',        'label' => 'Delivered',        'desc' => 'Enjoy your meal!',                 'icon' => ''],
];
$statusOrder  = array_column($steps, 'status');
$currentIdx   = array_search($currentStatus, $statusOrder);

$statusLabels = [
    'placed' => 'Order Placed', 'accepted' => 'Accepted', 'preparing' => 'Preparing',
    'out_for_delivery' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];

require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="container" style="padding:2rem 0 4rem">
  <div class="tracking-card">
    <div class="card card-body" id="trackCard">
      <?php if (!$orderId): ?>
        <div class="empty"><div class="empty-icon"></div><h3>No order ID</h3></div>
      <?php elseif (!$order): ?>
        <div class="empty"><div class="empty-icon"></div><h3>Order not found</h3></div>
      <?php else: ?>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
          <div>
            <h2 style="font-size:1.1rem;font-weight:800">Order #<?= strtoupper(substr($orderId, -6)) ?></h2>
          </div>
          <span class="status-badge status-<?= htmlspecialchars($currentStatus) ?>"><?= htmlspecialchars($statusLabels[$currentStatus] ?? $currentStatus) ?></span>
        </div>

        <div class="track-steps" id="trackSteps">
          <?php foreach ($steps as $i => $step):
            $done   = $i < $currentIdx;
            $active = $i === $currentIdx;
          ?>
          <div class="track-step">
            <div class="step-dot <?= $done ? 'done' : ($active ? 'active' : '') ?>">
              <?= $done ? '' : $step['icon'] ?>
            </div>
            <div class="step-info">
              <h4 style="<?= $active ? 'color:var(--primary)' : ($done ? '' : 'color:var(--text-muted)') ?>"><?= $step['label'] ?></h4>
              <p><?= $step['desc'] ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (!in_array($currentStatus, ['delivered', 'cancelled'])): ?>
        <div id="etaBox" style="background:var(--card-2);border-radius:8px;padding:1rem;text-align:center;margin-top:1rem">
          <p class="text-muted text-sm"> Estimated time: <strong id="etaValue" style="color:var(--text)"><?= htmlspecialchars($order['eta'] ?? '30 mins') ?></strong></p>
          <p class="text-muted text-sm" style="margin-top:.25rem">Auto-refreshing every 10 seconds...</p>
        </div>
        <?php endif; ?>

        <div style="margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap">
          <a href="/food_tracking_system/public/orders.php" class="btn btn-ghost btn-sm">← My Orders</a>
          <a href="/food_tracking_system/public/index.php" class="btn btn-outline btn-sm">Order More</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</main>

<?php if ($orderId && $order && !in_array($currentStatus, ['delivered', 'cancelled'])): ?>
<script>
const ORDER_ID = '<?= htmlspecialchars($orderId) ?>';
const STEPS = [
  { status:'placed',           label:'Order Placed',      desc:'Your order has been received',       icon:'' },
  { status:'accepted',         label:'Accepted',          desc:'Restaurant confirmed your order',    icon:'' },
  { status:'preparing',        label:'Preparing',         desc:'Chef is cooking your meal',          icon:'‍' },
  { status:'out_for_delivery', label:'Out for Delivery',  desc:'Rider is on the way to you',         icon:'' },
  { status:'delivered',        label:'Delivered',         desc:'Enjoy your meal!',                   icon:'' },
];

let pollTimer;

async function pollStatus() {
  const data = await apiFetch('order/' + ORDER_ID + '/status');
  if (!data.success) return;
  const order   = data.order;
  const current = order.status || 'placed';
  const currentIdx = STEPS.findIndex(s => s.status === current);

  // Update status badge
  document.querySelector('.status-badge').className = `status-badge status-${current}`;
  document.querySelector('.status-badge').textContent = statusLabel(current);

  // Update steps
  document.getElementById('trackSteps').innerHTML = STEPS.map((s,i) => {
    const done   = i < currentIdx;
    const active = i === currentIdx;
    return `<div class="track-step">
      <div class="step-dot ${done?'done':active?'active':''}">${done?'':s.icon}</div>
      <div class="step-info">
        <h4 style="${active?'color:var(--primary)':done?'':'color:var(--text-muted)'}">${s.label}</h4>
        <p>${s.desc}</p>
      </div>
    </div>`;
  }).join('');

  // Update ETA value
  const etaVal = document.getElementById('etaValue');
  if (etaVal && order.eta) etaVal.textContent = order.eta;

  // Hide ETA box and stop polling on final status
  if (current === 'delivered' || current === 'cancelled') {
    clearInterval(pollTimer);
    const etaBox = document.getElementById('etaBox');
    if (etaBox) etaBox.remove();
  }
}

// Start polling — first poll after 10s (page already has fresh data)
pollTimer = setInterval(pollStatus, 10000);
</script>
<?php endif; ?>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
