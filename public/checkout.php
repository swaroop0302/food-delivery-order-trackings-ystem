<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Checkout';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/models/UserModel.php';

$sess = new SessionController();
$user = $sess->requireLogin();
$userModel = new UserModel();
$profile   = $userModel->findById($user['id']);
$addresses = $profile['addresses'] ?? [];

require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="container" style="padding:2rem 1.5rem;max-width:800px">
  <h1 class="section-title"> Checkout</h1>
  <div class="grid-2" style="align-items:start">
    <!-- Address -->
    <div>
      <div class="card card-body" style="margin-bottom:1rem">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem"> Delivery Address</h3>
        <?php if ($addresses): ?>
          <?php foreach ($addresses as $i => $addr): ?>
            <label style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;margin-bottom:.5rem;transition:var(--transition)" class="addr-opt">
              <input type="radio" name="address_sel" value="<?= $i ?>" <?= $i===0?'checked':'' ?> style="margin-top:.2rem">
              <div>
                <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($addr['street']??'') ?></div>
                <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars(($addr['city']??'').', '.($addr['pincode']??'')) ?></div>
                <?php if (!empty($addr['landmark'])): ?><div style="font-size:.78rem;color:var(--text-muted)">Near: <?= htmlspecialchars($addr['landmark']) ?></div><?php endif; ?>
              </div>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="padding:.75rem;border:1.5px solid var(--border);border-radius:8px;margin-top:.5rem">
          <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;margin-bottom:.75rem;cursor:pointer">
            <input type="radio" name="address_sel" value="new"> Enter new address
          </label>
          <div id="newAddrForm" style="display:none">
            <div class="form-group"><label>Street</label><input type="text" id="addrStreet" placeholder="123 Main St"></div>
            <div class="grid-2" style="gap:.5rem">
              <div class="form-group"><label>City</label><input type="text" id="addrCity"></div>
              <div class="form-group"><label>Pincode</label><input type="text" id="addrPincode"></div>
            </div>
            <div class="form-group"><label>Landmark</label><input type="text" id="addrLandmark" placeholder="Optional"></div>
          </div>
        </div>
      </div>

      <div class="card card-body">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:.75rem"> Order Notes</h3>
        <textarea id="orderNotes" rows="2" style="width:100%;background:var(--dark-2);border:1.5px solid var(--border);border-radius:8px;padding:.7rem 1rem;color:var(--text);resize:vertical" placeholder="Any special instructions..."></textarea>
      </div>
    </div>

    <!-- Order summary -->
    <div class="card card-body" id="orderSummary">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem"> Order Summary</h3>
      <div id="summaryItems"></div>
      <div style="border-top:1px solid var(--border);margin-top:1rem;padding-top:1rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.875rem"><span style="color:var(--text-muted)">Subtotal</span><span id="subtotal"></span></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:.4rem;font-size:.875rem"><span style="color:var(--text-muted)">Delivery Fee</span><span id="deliveryFee">₹30</span></div>
        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem;margin-top:.75rem"><span>Total</span><span id="grandTotal"></span></div>
      </div>
      <button class="btn btn-primary btn-block" style="margin-top:1.25rem" onclick="placeOrder()" id="placeBtn">Place Order →</button>
    </div>
  </div>
</div>
</main>

<script>
const ADDRESSES = <?= json_encode($addresses) ?>;
const deliveryFee = 30;

function renderSummary() {
  if (!cart.items.length) { window.location='/food_tracking_system/public/index.php'; return; }
  const sub = getCartTotal();
  document.getElementById('summaryItems').innerHTML = cart.items.map(i =>
    `<div style="display:flex;justify-content:space-between;margin-bottom:.5rem;font-size:.875rem">
      <span>${escHtml(i.name)} × ${i.quantity}</span>
      <span>₹${(i.price*i.quantity).toFixed(2)}</span>
    </div>`).join('');
  document.getElementById('subtotal').textContent   = '₹' + sub.toFixed(2);
  document.getElementById('grandTotal').textContent = '₹' + (sub + deliveryFee).toFixed(2);
}

document.querySelectorAll('input[name="address_sel"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('newAddrForm').style.display = r.value === 'new' ? 'block' : 'none';
  });
});

async function placeOrder() {
  const btn = document.getElementById('placeBtn');
  btn.disabled = true; btn.textContent = 'Placing order...';

  const sel = document.querySelector('input[name="address_sel"]:checked')?.value;
  let address = {};
  if (sel === 'new') {
    address = { street: document.getElementById('addrStreet').value, city: document.getElementById('addrCity').value, pincode: document.getElementById('addrPincode').value, landmark: document.getElementById('addrLandmark').value };
  } else if (sel !== undefined && ADDRESSES[sel]) {
    address = ADDRESSES[sel];
  }

  const payload = {
    restaurant_id:    cart.restaurant_id,
    items:            cart.items,
    delivery_address: address,
    notes:            document.getElementById('orderNotes').value,
  };

  const data = await apiFetch('order/place', { method:'POST', body: JSON.stringify(payload) });
  if (data.success) {
    clearCart();
    showToast('Order placed!', 'success');
    setTimeout(() => window.location = '/food_tracking_system/public/track.php?id=' + data.order_id, 1000);
  } else {
    showToast(data.error || 'Failed to place order', 'error');
    btn.disabled = false; btn.textContent = 'Place Order →';
  }
}

renderSummary();
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
