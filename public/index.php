<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Home';
$pageDesc  = 'Order food online from the best restaurants near you';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/RestaurantController.php';

$restCtrl    = new RestaurantController();
$restaurants = $restCtrl->getAll([]);

require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
  <section class="hero">
    <h1>Hungry? We've got you. </h1>
    <p>Order from hundreds of restaurants near you with real-time tracking</p>
    <div class="hero-search">
      <input type="text" id="heroSearch" placeholder="Search for restaurants, dishes, cuisines...">
      <button id="heroSearchBtn">Search</button>
    </div>
  </section>

  <div class="container" style="padding-top:2rem;padding-bottom:4rem">
    <div class="filters" id="filters">
      <span class="filter-chip active" data-filter="">All</span>
      <span class="filter-chip" data-filter="veg" data-veg="1"> Pure Veg</span>
      <span class="filter-chip" data-filter="rating"> Top Rated</span>
      <span class="filter-chip" data-filter="cuisine" data-cuisine="Indian"> Indian</span>
      <span class="filter-chip" data-filter="cuisine" data-cuisine="Chinese"> Chinese</span>
      <span class="filter-chip" data-filter="cuisine" data-cuisine="Italian"> Italian</span>
      <span class="filter-chip" data-filter="cuisine" data-cuisine="Fast Food"> Fast Food</span>
      <span class="filter-chip" data-filter="cuisine" data-cuisine="Beverages"> Beverages</span>
    </div>

    <h2 class="section-title">Restaurants near you <span id="restCount">(<?= count($restaurants) ?>)</span></h2>

    <div id="restaurantGrid" class="grid-4">
      <?php if (empty($restaurants)): ?>
        <div class="empty"><div class="empty-icon">️</div><h3>No restaurants found</h3></div>
      <?php else: foreach ($restaurants as $r):
        $rating   = number_format((float)($r['avg_rating'] ?? 0), 1);
        $cuisines = array_slice((array)($r['cuisine'] ?? []), 0, 3);
        $imgRaw   = $r['image'] ?? '';
        $img      = $imgRaw
          ? ($imgRaw[0] === 'h' ? htmlspecialchars($imgRaw) : '/food_tracking_system/public/uploads/' . htmlspecialchars($imgRaw))
          : 'https://placehold.co/400x250/1e1e3a/6b7aff?text=' . urlencode($r['name'] ?? 'Restaurant');
      ?>
      <article class="card restaurant-card" onclick="window.location='/food_tracking_system/public/restaurant.php?id=<?= htmlspecialchars($r['_id']) ?>'">
        <div class="thumb">
          <img src="<?= $img ?>" alt="<?= htmlspecialchars($r['name']) ?>" loading="lazy" onerror="this.src='https://placehold.co/400x250/1a1a2e/e8eaf6?text=Restaurant'">
          <div class="badge-row">
            <span class="badge <?= $r['is_veg'] ? 'badge-veg' : 'badge-nonveg' ?>"><?= $r['is_veg'] ? ' Veg' : ' Non-Veg' ?></span>
          </div>
        </div>
        <div class="info">
          <h3><?= htmlspecialchars($r['name']) ?></h3>
          <div class="meta">
            <span class="rating"> <?= $rating ?></span>
            <span><?= htmlspecialchars($r['city'] ?? '') ?></span>
            <span>₹<?= (int)($r['delivery_fee'] ?? 0) ?> delivery</span>
          </div>
          <div class="cuisine-tags">
            <?php foreach ($cuisines as $c): ?>
              <span class="cuisine-tag"><?= htmlspecialchars($c) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
      <?php endforeach; endif; ?>
    </div>
  </div>
</main>

<script>
let activeFilters = {};

function skeletonCards(n = 8) {
  const s = `<div class="card" style="overflow:hidden">
    <div style="height:180px;background:linear-gradient(90deg,var(--card) 25%,var(--card-2) 50%,var(--card) 75%);background-size:200% 100%;animation:shimmer 1.4s infinite"></div>
    <div style="padding:1rem">
      <div style="height:14px;width:70%;background:var(--card-2);border-radius:6px;margin-bottom:.6rem;animation:shimmer 1.4s infinite"></div>
      <div style="height:11px;width:50%;background:var(--card-2);border-radius:6px;animation:shimmer 1.4s infinite"></div>
    </div>
  </div>`;
  return Array(n).fill(s).join('');
}

async function loadRestaurants(filters = {}) {
  const grid = document.getElementById('restaurantGrid');
  grid.innerHTML = skeletonCards();
  const params = new URLSearchParams(filters).toString();
  const data = await apiFetch('restaurant' + (params ? '&' + params : ''));
  if (!data.success) { grid.innerHTML = '<div class="empty"><div class="empty-icon"></div><h3>Failed to load</h3></div>'; return; }
  const rests = data.restaurants || [];
  document.getElementById('restCount').textContent = `(${rests.length})`;
  if (!rests.length) { grid.innerHTML = '<div class="empty"><div class="empty-icon">️</div><h3>No restaurants found</h3></div>'; return; }
  grid.innerHTML = rests.map(r => restaurantCard(r)).join('');
}

function restaurantCard(r) {
  const rating = Number(r.avg_rating||0).toFixed(1);
  const cuisines = (r.cuisine||[]).slice(0,3).map(c=>`<span class="cuisine-tag">${escHtml(c)}</span>`).join('');
  const img = r.image
    ? (r.image.startsWith('http') ? r.image : `/food_tracking_system/public/uploads/${r.image}`)
    : `https://placehold.co/400x250/1e1e3a/6b7aff?text=${encodeURIComponent(r.name||'Restaurant')}`;
  return `
  <article class="card restaurant-card" onclick="window.location='/food_tracking_system/public/restaurant.php?id=${r._id}'">
    <div class="thumb">
      <img src="${img}" alt="${escHtml(r.name)}" loading="lazy" onerror="this.src='https://placehold.co/400x250/1a1a2e/e8eaf6?text=Restaurant'">
      <div class="badge-row">
        <span class="badge ${r.is_veg?'badge-veg':'badge-nonveg'}">${r.is_veg?' Veg':' Non-Veg'}</span>
      </div>
    </div>
    <div class="info">
      <h3>${escHtml(r.name)}</h3>
      <div class="meta">
        <span class="rating"> ${rating}</span>
        <span>${escHtml(r.city||'')}</span>
        <span>₹${r.delivery_fee||0} delivery</span>
      </div>
      <div class="cuisine-tags">${cuisines}</div>
    </div>
  </article>`;
}

let _searchTimer;
function searchRestaurants() {
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(() => {
    const q = (document.getElementById('heroSearch') || document.getElementById('globalSearch'))?.value?.trim();
    if (q) { activeFilters.search = q; loadRestaurants(activeFilters); }
    else { activeFilters = {}; loadRestaurants({}); }
  }, 350);
}

// Wire up everything after app.js (deferred) has loaded
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.filter-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      activeFilters = {};
      const f = chip.dataset.filter;
      if (f === 'veg') activeFilters.is_veg = 1;
      else if (f === 'rating') activeFilters.sort = 'rating';
      else if (f === 'cuisine') activeFilters.cuisine = chip.dataset.cuisine;
      loadRestaurants(activeFilters);
    });
  });

  document.getElementById('heroSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') searchRestaurants(); });
  document.getElementById('heroSearchBtn')?.addEventListener('click', searchRestaurants);
  document.getElementById('globalSearch')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') { activeFilters.search = e.target.value; loadRestaurants(activeFilters); }
  });
});
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
