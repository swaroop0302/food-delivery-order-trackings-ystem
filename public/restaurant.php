<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Restaurant Menu';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/controllers/RestaurantController.php';
require_once ROOT_DIR . '/models/ReviewModel.php';

$restCtrl = new RestaurantController();
$id       = $_GET['id'] ?? '';
if (!$id) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$restaurant = $restCtrl->getById($id);
if (!$restaurant) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$restCtrl->setOnline($id);

$sess        = new SessionController();
$currentUser = $sess->getCurrentUser();

// Load reviews server-side
$reviewModel = new ReviewModel();
$reviews     = $reviewModel->findByRestaurant($id);

$imgRaw = $restaurant['image'] ?? '';
$img = ($imgRaw && $imgRaw !== 'default_restaurant.jpg')
     ? (str_starts_with($imgRaw, 'http') ? $imgRaw : '/food_tracking_system/public/uploads/' . $imgRaw)
     : 'https://placehold.co/1200x400/1e1e3a/6b7aff?text=' . urlencode($restaurant['name']);

$menuGroups = [];
foreach ($restaurant['menu_items'] ?? [] as $item) {
    $type = $item['type'] ?? 'veg';
    $menuGroups[$type][] = $item;
}

function timeAgoPhpR(string $iso): string {
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
<!-- Hero -->
<div class="menu-hero">
  <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($restaurant['name']) ?>" onerror="this.src='https://placehold.co/1200x400/16213e/e8eaf6?text=<?= urlencode($restaurant['name']) ?>'">
  <div class="overlay">
    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.5rem">
      <span class="badge <?= $restaurant['is_veg'] ? 'badge-veg' : 'badge-nonveg' ?>"><?= $restaurant['is_veg'] ? ' Pure Veg' : ' Non-Veg' ?></span>
      <?php if ($restCtrl->isOnline($id)): ?>
        <span class="badge" style="background:rgba(0,184,148,.8);color:#fff">🟢 Open</span>
      <?php endif; ?>
    </div>
    <h1><?= htmlspecialchars($restaurant['name']) ?></h1>
    <div style="display:flex;gap:1.5rem;margin-top:.4rem;font-size:.875rem;color:rgba(255,255,255,.8)">
      <span> <?= number_format((float)($restaurant['avg_rating']??0),1) ?> rating</span>
      <span> <?= htmlspecialchars($restaurant['city']??'') ?></span>
      <span> ₹<?= $restaurant['delivery_fee']??30 ?> delivery</span>
      <span> 30–40 mins</span>
    </div>
  </div>
</div>

<div class="container">
  <div class="menu-layout">
    <!-- Sidebar categories -->
    <aside class="menu-categories" id="menuSidebar">
      <h3>Categories</h3>
      <div id="catLinks">
        <?php
        $typeIcons  = ['veg' => '', 'non_veg' => '', 'beverage' => ''];
        $typeLabels = ['veg' => 'Vegetarian', 'non_veg' => 'Non-Vegetarian', 'beverage' => 'Beverages'];
        foreach (array_keys($menuGroups) as $type):
            $label = $typeLabels[$type] ?? $type;
            $icon  = $typeIcons[$type]  ?? '️';
        ?>
          <a class="cat-link" href="#cat-<?= htmlspecialchars($type) ?>"><?= $icon . ' ' . htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
      </div>
    </aside>

    <!-- Menu items -->
    <section id="menuContent">
      <?php if (empty($menuGroups)): ?>
        <div class="empty"><div class="empty-icon">️</div><h3>Menu coming soon</h3></div>
      <?php else: ?>
        <?php
        $typeIcons  = ['veg' => '', 'non_veg' => '', 'beverage' => ''];
        $typeLabels = ['veg' => 'Vegetarian', 'non_veg' => 'Non-Vegetarian', 'beverage' => 'Beverages'];
        foreach ($menuGroups as $type => $items):
            $label = $typeLabels[$type] ?? $type;
            $icon  = $typeIcons[$type]  ?? '️';
        ?>
        <div class="menu-section" id="cat-<?= htmlspecialchars($type) ?>">
          <h2><?= $icon . ' ' . htmlspecialchars($label) ?></h2>
          <?php foreach ($items as $item):
            $available  = ($item['is_available'] ?? true) !== false;
            $itemJson   = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
            $badgeClass = $type === 'veg' ? 'badge-veg' : ($type === 'beverage' ? 'badge-beverage' : 'badge-nonveg');
            $dot        = $type === 'veg' ? '●' : ($type === 'beverage' ? '◆' : '■');
            if ($type === 'veg' && !empty($item['is_jain'])) {
                $extraBadge = '<span class="badge badge-jain">Jain</span>';
            } elseif ($type === 'non_veg') {
                $sl = htmlspecialchars($item['spice_level'] ?? 'medium');
                $extraBadge = "<span class=\"badge\" style=\"background:rgba(226,55,68,.15);color:var(--danger);font-size:.7rem\"> {$sl}</span>";
            } elseif ($type === 'beverage') {
                $ml = (int)($item['serving_size_ml'] ?? 250);
                $extraBadge = "<span class=\"text-muted text-sm\">{$ml}ml</span>";
            } else {
                $extraBadge = '';
            }
          ?>
          <div class="menu-item" id="mi-<?= htmlspecialchars($item['_id']) ?>">
            <div class="item-info">
              <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
                <span class="badge <?= $badgeClass ?>" style="font-size:.65rem"><?= $dot ?></span>
                <span class="item-name"><?= htmlspecialchars($item['name']) ?></span>
              </div>
              <div class="item-meta"><?= $extraBadge ?></div>
              <div class="item-price">₹<?= number_format((float)$item['price'], 2) ?></div>
            </div>
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:10px;flex-shrink:0;margin-left:auto">
            <?php endif; ?>
            <div id="ctrl-<?= htmlspecialchars($item['_id']) ?>">
              <button class="btn btn-outline btn-sm add-btn" <?= !$available ? 'disabled' : '' ?> onclick="handleAdd(<?= $itemJson ?>)">
                <?= $available ? '+ Add' : 'Unavailable' ?>
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</div>

<!-- Review section -->
<div class="container" style="padding-bottom:3rem">
  <div class="card card-body" style="max-width:700px">
    <h2 class="section-title" style="margin-bottom:1rem"> Reviews</h2>
    <div id="reviewsList">
      <?php if (empty($reviews)): ?>
        <p class="text-muted text-sm">No reviews yet. Be the first!</p>
      <?php else: foreach ($reviews as $r): ?>
        <div style="padding:.75rem 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
            <strong style="font-size:.9rem"><?= htmlspecialchars($r['user_name'] ?? 'Anonymous') ?></strong>
            <span style="color:var(--warning)"><?= str_repeat('', min(5, (int)round($r['rating'] ?? 5))) ?></span>
            <span class="text-muted text-sm"><?= timeAgoPhpR($r['created_at'] ?? '') ?></span>
          </div>
          <p style="font-size:.875rem;color:var(--text-muted)"><?= htmlspecialchars($r['comment'] ?? '') ?></p>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php if ($currentUser && $currentUser['role'] === 'user'): ?>
    <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border)">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Leave a Review</h3>
      <div class="form-group">
        <label>Rating</label>
        <select id="reviewRating">
          <option value="5"> (5)</option>
          <option value="4"> (4)</option>
          <option value="3"> (3)</option>
          <option value="2"> (2)</option>
          <option value="1"> (1)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Comment</label>
        <textarea id="reviewComment" rows="3" style="width:100%;background:var(--dark-2);border:1.5px solid var(--border);border-radius:8px;padding:.7rem 1rem;color:var(--text);resize:vertical" placeholder="Share your experience..."></textarea>
      </div>
      <button class="btn btn-primary" onclick="submitReview()">Submit Review</button>
    </div>
    <?php endif; ?>
  </div>
</div>
</main>

<script>
const RESTAURANT_ID   = '<?= $id ?>';
const RESTAURANT_NAME = <?= json_encode($restaurant['name']) ?>;

// Map of item_id → item data for re-rendering controls
const MENU_ITEMS = {};
<?php foreach ($menuGroups as $items): foreach ($items as $item): ?>
MENU_ITEMS[<?= json_encode($item['_id']) ?>] = <?= json_encode([
    '_id'   => $item['_id'],
    'name'  => $item['name'],
    'price' => $item['price'],
    'type'  => $item['type'],
    'is_available' => $item['is_available'] ?? true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
<?php endforeach; endforeach; ?>

function renderItemCtrl(itemId) {
  const ctrl = document.getElementById('ctrl-' + itemId);
  if (!ctrl) return;
  const cartItem = cart.items.find(i => i.item_id === itemId);
  const meta = MENU_ITEMS[itemId];
  if (cartItem) {
    ctrl.innerHTML = `<div class="qty-ctrl">
      <button onclick="updateQty('${itemId}',-1)">−</button>
      <span id="qty-${itemId}">${cartItem.quantity}</span>
      <button onclick="updateQty('${itemId}',1)">+</button>
    </div>`;
  } else {
    const available = meta ? meta.is_available !== false : true;
    ctrl.innerHTML = `<button class="btn btn-outline btn-sm add-btn" ${!available ? 'disabled' : ''} onclick="handleAdd(MENU_ITEMS['${itemId}'])">+ Add</button>`;
  }
}

function handleAdd(item) {
  addToCart({ item_id: item._id, name: item.name, price: item.price, type: item.type }, RESTAURANT_ID, RESTAURANT_NAME);
  renderItemCtrl(item._id);
}

// Override updateQty to also refresh the menu item control
const _origUpdate = updateQty;
window.updateQty = function(itemId, delta) {
  _origUpdate(itemId, delta);
  renderItemCtrl(itemId);
};

async function loadReviews() {
  const list = document.getElementById('reviewsList');
  try {
    const data = await apiFetch('review/' + RESTAURANT_ID);
    if (!data.reviews?.length) { list.innerHTML = '<p class="text-muted text-sm">No reviews yet. Be the first!</p>'; return; }
    list.innerHTML = data.reviews.map(r => `
      <div style="padding:.75rem 0;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.3rem">
          <strong style="font-size:.9rem">${escHtml(r.user_name||'Anonymous')}</strong>
          <span style="color:var(--warning)">${''.repeat(Math.round(r.rating||5))}</span>
          <span class="text-muted text-sm">${timeAgo(r.created_at)}</span>
        </div>
        <p style="font-size:.875rem;color:var(--text-muted)">${escHtml(r.comment||'')}</p>
      </div>`).join('');
  } catch(e) {
    list.innerHTML = '<p class="text-muted text-sm">Could not load reviews.</p>';
  }
}

async function submitReview() {
  const rating  = document.getElementById('reviewRating').value;
  const comment = document.getElementById('reviewComment').value;
  const data = await apiFetch('review', { method:'POST', body: JSON.stringify({ restaurant_id: RESTAURANT_ID, rating, comment }) });
  if (data.success) { showToast('Review submitted!','success'); loadReviews(); }
  else showToast(data.error||'Failed','error');
}

loadReviews();
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
