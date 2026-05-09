<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Restaurant Dashboard';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/controllers/RestaurantController.php';
require_once ROOT_DIR . '/controllers/OrderController.php';
require_once ROOT_DIR . '/models/RestaurantModel.php';

$sess   = new SessionController();
$user   = $sess->requireRole('restaurant');
$restId = $user['id'];

$restCtrl  = new RestaurantController();
$restCtrl->setOnline($restId);

$restModel  = new RestaurantModel();
$restaurant = $restModel->findById($restId);
if (!$restaurant) { header('Location: ' . BASE_URL . '/login.php'); exit; }

$orderCtrl = new OrderController();
$orders    = $orderCtrl->getRestaurantOrders($restId);

// Group menu by type
$menuGroups = [];
foreach ($restaurant['menu_items'] ?? [] as $item) {
    $menuGroups[$item['type'] ?? 'veg'][] = $item;
}

$statusLabels = [
    'placed' => 'Order Placed', 'accepted' => 'Accepted', 'preparing' => 'Preparing',
    'out_for_delivery' => 'On the way', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
];

function timeAgoPhpD(string $iso): string {
    if (!$iso) return '';
    $diff = time() - strtotime($iso);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('d M Y', strtotime($iso));
}

require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="container" style="padding:2rem 0 4rem">

  <!-- Cover Photo -->
  <div style="position:relative;border-radius:16px;overflow:hidden;margin-bottom:2rem;height:200px;background:var(--card-bg)">
    <img id="coverImg"
      src="<?= !empty($restaurant['image']) && str_starts_with($restaurant['image'],'http') ? htmlspecialchars($restaurant['image']) : (!empty($restaurant['image']) ? BASE_URL.'/uploads/'.htmlspecialchars($restaurant['image']) : '') ?>"
      alt="Cover"
      style="width:100%;height:100%;object-fit:cover;display:<?= !empty($restaurant['image']) ? 'block' : 'none' ?>">
    <div id="coverPlaceholder" style="display:<?= !empty($restaurant['image']) ? 'none' : 'flex' ?>;align-items:center;justify-content:center;height:100%;flex-direction:column;gap:.5rem;color:var(--text-muted)">
      <span style="font-size:3rem"></span><span style="font-size:.9rem">No cover photo yet</span>
    </div>
    <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.7) 0%,transparent 55%)"></div>
    <div style="position:absolute;bottom:1rem;left:1.25rem;right:8rem">
      <h1 style="margin:0;font-size:1.6rem;color:#fff;text-shadow:0 1px 4px rgba(0,0,0,.5)"> <?= htmlspecialchars($restaurant['name']) ?></h1>
      <p style="margin:.2rem 0 0;font-size:.85rem;color:rgba(255,255,255,.8)">Restaurant Dashboard · <span style="color:#4caf50">🟢 Online</span></p>
    </div>
    <label for="coverInput" style="position:absolute;bottom:1rem;right:1rem;cursor:pointer">
      <span class="btn btn-outline btn-sm" style="background:rgba(0,0,0,.55);border-color:rgba(255,255,255,.3);color:#fff;backdrop-filter:blur(4px)"> Change Photo</span>
      <input type="file" id="coverInput" accept="image/*" style="display:none" onchange="uploadCover(event)">
    </label>
  </div>

  <!-- Action buttons -->
  <div style="display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <button class="btn btn-outline btn-sm" onclick="showTab('orders')"> Orders</button>
    <button class="btn btn-outline btn-sm" onclick="showTab('menu')"> Menu</button>
    <button class="btn btn-primary btn-sm" onclick="openAddItem()">+ Add Item</button>
  </div>

  <!-- Stats -->
  <div class="dashboard-grid" style="margin-bottom:2rem">
    <div class="stat-card red"><div class="stat-icon"></div><div class="stat-val"><?= (int)($restaurant['total_orders']??0) ?></div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card orange"><div class="stat-icon"></div><div class="stat-val">₹<?= number_format((float)($restaurant['total_revenue']??0)) ?></div><div class="stat-label">Total Revenue</div></div>
    <div class="stat-card green"><div class="stat-icon"></div><div class="stat-val"><?= number_format((float)($restaurant['avg_rating']??0),1) ?></div><div class="stat-label">Avg Rating</div></div>
    <div class="stat-card blue"><div class="stat-icon">️</div><div class="stat-val"><?= count($restaurant['menu_items']??[]) ?></div><div class="stat-label">Menu Items</div></div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab active" id="tab-orders" onclick="showTab('orders')"> Incoming Orders</div>
    <div class="tab" id="tab-menu" onclick="showTab('menu')">️ Menu Management</div>
  </div>

  <!-- Orders Tab (SSR) -->
  <div id="pane-orders">
    <?php if (empty($orders)): ?>
      <div class="empty"><div class="empty-icon"></div><h3>No orders yet</h3></div>
    <?php else: ?>
      <table class="dash-table">
        <thead><tr><th>Order ID</th><th>Items</th><th>Total</th><th>Status</th><th>Time</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o):
            $statusKey  = $o['status'] ?? 'placed';
            $statusText = $statusLabels[$statusKey] ?? $statusKey;
            $itemStr    = implode(', ', array_map(fn($i) => htmlspecialchars($i['name']).'×'.(int)$i['quantity'], $o['items'] ?? []));
            $shortId    = strtoupper(substr($o['_id'], -6));
          ?>
          <tr>
            <td><code>#<?= $shortId ?></code></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $itemStr ?></td>
            <td>₹<?= number_format((float)$o['total_price'], 2) ?></td>
            <td><span class="status-badge status-<?= htmlspecialchars($statusKey) ?>"><?= htmlspecialchars($statusText) ?></span></td>
            <td class="text-muted text-sm"><?= timeAgoPhpD($o['placed_at'] ?? '') ?></td>
            <td>
              <select class="btn btn-ghost btn-sm" onchange="updateStatus('<?= htmlspecialchars($o['_id']) ?>',this.value)" style="border:1px solid var(--border);padding:.3rem">
                <option value="">Update...</option>
                <?php foreach (['accepted','preparing','out_for_delivery','delivered','cancelled'] as $s): ?>
                  <option value="<?= $s ?>"><?= $statusLabels[$s] ?? $s ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Menu Tab (SSR) -->
  <div id="pane-menu" style="display:none">
    <?php if (empty($menuGroups)): ?>
      <div class="empty"><div class="empty-icon">️</div><h3>No items yet. Add your first!</h3></div>
    <?php else:
      $typeIcons  = ['veg' => '', 'non_veg' => '', 'beverage' => ''];
      $typeLabels = ['veg' => 'Vegetarian', 'non_veg' => 'Non-Vegetarian', 'beverage' => 'Beverages'];
      foreach ($menuGroups as $type => $items):
    ?>
      <h3 style="font-size:1rem;font-weight:700;margin:1.25rem 0 .75rem"><?= ($typeIcons[$type]??'') . ' ' . ($typeLabels[$type]??$type) ?></h3>
      <table class="dash-table">
        <thead><tr><th style="width:48px">Img</th><th>Name</th><th>Price</th><th>Extra</th><th>Available</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item):
            $extra = $type === 'veg'
              ? 'Jain: ' . (!empty($item['is_jain']) ? 'Yes' : 'No')
              : ($type === 'non_veg'
                ? 'Spice: ' . htmlspecialchars($item['spice_level'] ?? 'medium')
                : 'Size: ' . (int)($item['serving_size_ml'] ?? 250) . 'ml');
            $avail = ($item['is_available'] ?? true) !== false;
            $itemJsonAttr = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
          ?>
          <tr>
            <td style="width:48px;text-align:center;vertical-align:middle">
              <?php if (!empty($item['image_url'])): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;display:block;margin:0 auto">
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:1.2rem">️</span>
              <?php endif; ?>
            </td>
            <td style="vertical-align:middle"><?= htmlspecialchars($item['name']) ?></td>
            <td style="white-space:nowrap;vertical-align:middle">₹<?= number_format((float)$item['price'], 2) ?></td>
            <td class="text-muted text-sm" style="white-space:nowrap;vertical-align:middle"><?= $extra ?></td>
            <td style="text-align:center;vertical-align:middle"><span style="color:<?= $avail ? 'var(--success)' : 'var(--danger)' ?>"><?= $avail ? '' : '' ?></span></td>
            <td style="white-space:nowrap;vertical-align:middle">
              <button class="btn btn-ghost btn-sm" onclick='editItem(<?= $itemJsonAttr ?>)'>Edit</button>
              <button class="btn btn-sm" style="background:rgba(226,55,68,.15);color:var(--danger);margin-left:.35rem" onclick="deleteItem('<?= htmlspecialchars($item['_id']) ?>')">Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; endif; ?>
  </div>
</div>
</main>

<!-- Add/Edit Menu Item Modal -->
<div class="modal-backdrop" id="itemModal">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Add Menu Item</h3>
      <button class="modal-close" onclick="closeModal()"></button>
    </div>
    <form id="itemForm" onsubmit="submitItem(event)">
      <input type="hidden" id="itemId">
      <div class="form-group"><label>Name</label><input type="text" id="iName" required placeholder="Paneer Tikka"></div>
      <div class="form-group"><label>Price (₹)</label><input type="number" id="iPrice" required min="1" step="0.5"></div>
      <div class="form-group">
        <label>Type</label>
        <select id="iType" onchange="updateTypeFields()">
          <option value="veg"> Vegetarian</option>
          <option value="non_veg"> Non-Vegetarian</option>
          <option value="beverage"> Beverage</option>
        </select>
      </div>
      <div id="vegFields"><div class="form-group" style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" id="iJain" style="width:auto;margin:0"><label for="iJain" style="margin:0;cursor:pointer">Jain friendly</label></div></div>
      <div id="nonvegFields" style="display:none"><div class="form-group"><label>Spice Level</label><select id="iSpice"><option value="low"> Low</option><option value="medium" selected> Medium</option><option value="high"> High</option></select></div></div>
      <div id="bevFields" style="display:none"><div class="form-group"><label>Serving Size (ml)</label><input type="number" id="iServing" value="250" min="1"></div></div>
      <div class="form-group">
        <label>Item Image (optional, max 5 MB)</label>
        <div id="imgUploadArea" style="border:2px dashed var(--border);border-radius:10px;padding:1rem;text-align:center;cursor:pointer" onclick="document.getElementById('iImage').click()" ondragover="event.preventDefault()" ondrop="handleImgDrop(event)">
          <img id="imgPreview" src="" alt="" style="max-width:100%;max-height:140px;border-radius:8px;display:none;margin-bottom:.5rem">
          <div id="imgPlaceholder" style="color:var(--text-muted);font-size:.9rem"> Click or drag &amp; drop image here</div>
          <input type="file" id="iImage" accept="image/*" style="display:none" onchange="previewImg(event)">
        </div>
        <input type="hidden" id="iImageUrl" value="">
      </div>
      <div class="form-group" style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" id="iAvail" checked style="width:auto;margin:0"><label for="iAvail" style="margin:0;cursor:pointer">Available</label></div>
      <button type="submit" class="btn btn-primary btn-block" id="itemSubmitBtn">Save Item</button>
    </form>
  </div>
</div>

<script>
const RESTAURANT_ID = '<?= $restId ?>';

function showTab(name) {
  ['orders','menu'].forEach(t => {
    document.getElementById('pane-'+t).style.display = t===name ? 'block':'none';
    document.getElementById('tab-'+t).classList.toggle('active', t===name);
  });
}

async function updateStatus(orderId, status) {
  if (!status) return;
  const data = await apiFetch('order/'+orderId+'/status', { method:'PATCH', body: JSON.stringify({status}) });
  if (data.success) { showToast('Status updated to '+statusLabel(status),'success'); setTimeout(()=>location.reload(),800); }
  else showToast(data.error||'Failed','error');
}

async function uploadCover(event) {
  const file = event.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => { const img = document.getElementById('coverImg'); img.src = e.target.result; img.style.display = 'block'; document.getElementById('coverPlaceholder').style.display = 'none'; };
  reader.readAsDataURL(file);
  const formData = new FormData();
  formData.append('image', file);
  try {
    const res  = await fetch(`/food_tracking_system/public/api.php?route=restaurant/${RESTAURANT_ID}/upload-image`, { method:'POST', body:formData });
    const data = await res.json();
    if (data.success) {
      await apiFetch('restaurant/'+RESTAURANT_ID, { method:'PATCH', body: JSON.stringify({ image: data.filename }) });
      document.getElementById('coverImg').src = data.url;
      showToast('Cover photo updated!','success');
    } else showToast(data.error||'Upload failed','error');
  } catch(e) { showToast('Upload failed','error'); }
  event.target.value = '';
}

function openAddItem() {
  document.getElementById('itemId').value='';
  document.getElementById('itemForm').reset();
  document.getElementById('iImageUrl').value='';
  document.getElementById('imgPreview').src=''; document.getElementById('imgPreview').style.display='none';
  document.getElementById('imgPlaceholder').style.display='block';
  document.getElementById('modalTitle').textContent='Add Menu Item';
  document.getElementById('itemModal').classList.add('open');
  updateTypeFields();
}
function closeModal() { document.getElementById('itemModal').classList.remove('open'); }

function editItem(item) {
  document.getElementById('itemId').value  = item._id;
  document.getElementById('iName').value   = item.name;
  document.getElementById('iPrice').value  = item.price;
  document.getElementById('iType').value   = item.type || 'veg';
  document.getElementById('iJain').checked = !!item.is_jain;
  if (item.spice_level) document.getElementById('iSpice').value = item.spice_level;
  if (item.serving_size_ml) document.getElementById('iServing').value = item.serving_size_ml;
  document.getElementById('iAvail').checked = item.is_available !== false;
  document.getElementById('iImageUrl').value = item.image_url || '';
  if (item.image_url) { document.getElementById('imgPreview').src=item.image_url; document.getElementById('imgPreview').style.display='block'; document.getElementById('imgPlaceholder').style.display='none'; }
  else { document.getElementById('imgPreview').src=''; document.getElementById('imgPreview').style.display='none'; document.getElementById('imgPlaceholder').style.display='block'; }
  document.getElementById('modalTitle').textContent='Edit Menu Item';
  document.getElementById('itemModal').classList.add('open');
  updateTypeFields();
}

function updateTypeFields() {
  const t = document.getElementById('iType').value;
  document.getElementById('vegFields').style.display    = t==='veg'      ? 'block':'none';
  document.getElementById('nonvegFields').style.display = t==='non_veg'  ? 'block':'none';
  document.getElementById('bevFields').style.display    = t==='beverage' ? 'block':'none';
}

function previewImg(event) {
  const file = event.target.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = e => { document.getElementById('imgPreview').src=e.target.result; document.getElementById('imgPreview').style.display='block'; document.getElementById('imgPlaceholder').style.display='none'; };
  reader.readAsDataURL(file);
}

function handleImgDrop(event) {
  event.preventDefault();
  const file = event.dataTransfer.files[0]; if (!file) return;
  document.getElementById('iImage').files = event.dataTransfer.files;
  const reader = new FileReader();
  reader.onload = e => { document.getElementById('imgPreview').src=e.target.result; document.getElementById('imgPreview').style.display='block'; document.getElementById('imgPlaceholder').style.display='none'; };
  reader.readAsDataURL(file);
}

async function submitItem(e) {
  e.preventDefault();
  const btn = document.getElementById('itemSubmitBtn');
  btn.disabled = true; btn.textContent = 'Saving...';
  const id   = document.getElementById('itemId').value;
  const type = document.getElementById('iType').value;
  const body = { name: document.getElementById('iName').value, price: +document.getElementById('iPrice').value, type, is_available: document.getElementById('iAvail').checked };
  if (type==='veg')      body.is_jain = document.getElementById('iJain').checked;
  if (type==='non_veg')  body.spice_level = document.getElementById('iSpice').value;
  if (type==='beverage') body.serving_size_ml = +document.getElementById('iServing').value;
  const fileInput = document.getElementById('iImage');
  if (fileInput.files.length) {
    const formData = new FormData(); formData.append('image', fileInput.files[0]);
    try {
      const res = await fetch(`/food_tracking_system/public/api.php?route=restaurant/${RESTAURANT_ID}/upload-image`, { method:'POST', body:formData });
      const d   = await res.json();
      if (d.success) body.image_url = d.url;
    } catch(e) {}
  } else {
    const existing = document.getElementById('iImageUrl').value;
    if (existing) body.image_url = existing;
  }
  const route  = id ? 'restaurant/'+RESTAURANT_ID+'/menu/'+id : 'restaurant/'+RESTAURANT_ID+'/menu';
  const method = id ? 'PATCH' : 'POST';
  const data   = await apiFetch(route, { method, body: JSON.stringify(body) });
  btn.disabled = false; btn.textContent = 'Save Item';
  if (data.success) { showToast(id?'Item updated!':'Item added!','success'); closeModal(); setTimeout(()=>location.reload(),600); }
  else showToast(data.error||JSON.stringify(data.errors)||'Failed','error');
}

async function deleteItem(itemId) {
  if (!confirm('Delete this item?')) return;
  const data = await apiFetch('restaurant/'+RESTAURANT_ID+'/menu/'+itemId, { method:'DELETE' });
  if (data.success) { showToast('Item deleted','success'); setTimeout(()=>location.reload(),600); }
  else showToast('Failed','error');
}

setInterval(() => apiFetch('restaurant/'+RESTAURANT_ID+'/heartbeat', {method:'POST'}), 60000);
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
