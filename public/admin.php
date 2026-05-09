<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Admin Dashboard';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
$sess = new SessionController();
$sess->requireRole('admin');
require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="container" style="padding:2rem 0 4rem">
  <div class="page-header"><h1>️ Admin Dashboard</h1></div>

  <!-- Stats -->
  <div class="dashboard-grid" id="statsRow"><div class="spinner"></div></div>

  <!-- Tabs -->
  <div class="tabs" style="margin-top:1.5rem">
    <div class="tab active" id="tab-overview"  onclick="showTab('overview')"> Overview</div>
    <div class="tab" id="tab-orders"    onclick="showTab('orders')"> Orders</div>
    <div class="tab" id="tab-users"     onclick="showTab('users')"> Users</div>
    <div class="tab" id="tab-rests"     onclick="showTab('rests')"> Restaurants</div>
  </div>

  <div id="pane-overview"><div class="spinner"></div></div>
  <div id="pane-orders"   style="display:none"></div>
  <div id="pane-users"    style="display:none"></div>
  <div id="pane-rests"    style="display:none"></div>
</div>
</main>

<script>
let analytics = null;

async function loadAnalytics() {
  const data = await apiFetch('dashboard/analytics');
  if (!data.success) { document.getElementById('statsRow').innerHTML='<p class="text-danger">Failed to load analytics</p>'; return; }
  analytics = data.analytics;

  document.getElementById('statsRow').innerHTML = `
    <div class="stat-card red"><div class="stat-icon"></div><div class="stat-val">${analytics.total_users}</div><div class="stat-label">Users</div></div>
    <div class="stat-card orange"><div class="stat-icon"></div><div class="stat-val">${analytics.total_restaurants}</div><div class="stat-label">Restaurants</div></div>
    <div class="stat-card green"><div class="stat-icon"></div><div class="stat-val">${analytics.total_orders}</div><div class="stat-label">Total Orders</div></div>
    <div class="stat-card blue"><div class="stat-icon"></div><div class="stat-val">${analytics.delivery_queue_len}</div><div class="stat-label">Queue Length</div></div>
    <div class="stat-card green"><div class="stat-icon">🟢</div><div class="stat-val">${analytics.online_restaurants}</div><div class="stat-label">Online Now</div></div>
    <div class="stat-card orange"><div class="stat-icon"></div><div class="stat-val">${(analytics.active_orders||[]).length}</div><div class="stat-label">Active (Redis)</div></div>
    <div class="stat-card blue"><div class="stat-icon"></div><div class="stat-val">${analytics.avg_delivery_time}m</div><div class="stat-label">Avg Delivery</div></div>`;

  renderOverview();
}

function renderOverview() {
  if (!analytics) return;
  const byStatus = analytics.orders_by_status || {};
  document.getElementById('pane-overview').innerHTML = `
    <div class="grid-2" style="margin-top:1rem">
      <div class="card card-body">
        <h3 class="section-title" style="font-size:1rem;margin-bottom:1rem"> Orders by Status</h3>
        ${Object.entries(byStatus).map(([s,n]) => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border)">
            <span class="status-badge status-${s}">${statusLabel(s)}</span>
            <strong>${n}</strong>
          </div>`).join('')}
      </div>
      <div class="card card-body">
        <h3 class="section-title" style="font-size:1rem;margin-bottom:1rem"> Most Ordered Items</h3>
        ${(analytics.most_ordered_items||[]).slice(0,8).map((item,i) => `
          <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px solid var(--border)">
            <span style="font-size:.875rem">${i+1}. ${escHtml(item.name||'')}</span>
            <span class="text-muted text-sm">${item.total_qty} orders</span>
          </div>`).join('')}
      </div>
    </div>

    <div class="card card-body" style="margin-top:1.5rem">
      <h3 class="section-title" style="font-size:1rem;margin-bottom:1rem"> Revenue by Restaurant</h3>
      <table class="dash-table">
        <thead><tr><th>Restaurant</th><th>Total Orders</th><th>Total Revenue</th><th>Avg Rating</th></tr></thead>
        <tbody>
          ${(analytics.revenue_by_restaurant||[]).map(r=>`
            <tr>
              <td><strong>${escHtml(r.name||r._id||'')}</strong></td>
              <td>${r.total_orders||0}</td>
              <td style="color:var(--success);font-weight:700">₹${Number(r.total_revenue||0).toLocaleString()}</td>
              <td><span class="badge" style="background:#00b894;color:#fff"> ${Number(r.avg_rating||0).toFixed(1)}</span></td>
            </tr>`).join('')}
        </tbody>
      </table>
    </div>

    <div class="card card-body" style="margin-top:1.5rem">
      <h3 class="section-title" style="font-size:1rem;margin-bottom:1rem"> Active Orders (Redis)</h3>
      ${!(analytics.active_orders||[]).length ? '<p class="text-muted text-sm">No active orders in Redis</p>' :
        `<table class="dash-table"><thead><tr><th>Order ID</th><th>Status</th><th>User</th><th>Restaurant</th><th>ETA</th></tr></thead><tbody>
          ${(analytics.active_orders||[]).map(o=>`<tr>
            <td><code>${o.id?.slice(-6).toUpperCase()||''}</code></td>
            <td><span class="status-badge status-${o.status}">${statusLabel(o.status)}</span></td>
            <td class="text-muted text-sm">${o.user_id||''}</td>
            <td class="text-muted text-sm">${o.restaurant_id||''}</td>
            <td class="text-muted text-sm">${o.eta||'—'}</td>
          </tr>`).join('')}
        </tbody></table>`}
    </div>`;
}

async function loadUsersTab() {
  const data = await apiFetch('user/all');
  // NOTE: full user list not exposed via API for security — show message
  document.getElementById('pane-users').innerHTML = `
    <div class="card card-body" style="margin-top:1rem">
      <h3 style="font-weight:700;margin-bottom:.5rem">Total Users: ${analytics?.total_users||'—'}</h3>
      <p class="text-muted text-sm">Detailed user management available via MongoDB admin panel for data security.</p>
    </div>`;
}

async function loadRestsTab() {
  const data = await apiFetch('restaurant');
  const el   = document.getElementById('pane-rests');
  el.innerHTML = `<div class="card" style="margin-top:1rem">
    <table class="dash-table">
      <thead><tr><th>Name</th><th>City</th><th>Cuisine</th><th>Orders</th><th>Revenue</th><th>Rating</th></tr></thead>
      <tbody>
        ${(data.restaurants||[]).map(r=>`<tr>
          <td><a href="/food_tracking_system/public/restaurant.php?id=${r._id}" style="color:var(--primary)">${escHtml(r.name)}</a></td>
          <td class="text-muted">${escHtml(r.city||'')}</td>
          <td class="text-muted text-sm">${(r.cuisine||[]).join(', ')}</td>
          <td>${r.total_orders||0}</td>
          <td style="color:var(--success)">₹${Number(r.total_revenue||0).toLocaleString()}</td>
          <td> ${Number(r.avg_rating||0).toFixed(1)}</td>
        </tr>`).join('')}
      </tbody>
    </table>
  </div>`;
}

async function loadOrdersTab() {
  document.getElementById('pane-orders').innerHTML = `
    <div class="card card-body" style="margin-top:1rem">
      <p class="text-muted text-sm">Real-time order data shown in the Overview tab. Full order list available here.</p>
      <div style="margin-top:.75rem;display:flex;gap:.75rem">
        <button class="btn btn-outline btn-sm" onclick="assignDelivery()"> Assign Next Delivery</button>
        <button class="btn btn-ghost btn-sm" onclick="loadAnalytics()"> Refresh</button>
      </div>
      <div id="assignResult" style="margin-top:1rem"></div>
    </div>`;
}

async function assignDelivery() {
  const data = await apiFetch('order/assign', {method:'POST'});
  const el   = document.getElementById('assignResult');
  if (data.assignment) {
    el.innerHTML = `<div style="background:rgba(0,184,148,.1);padding:1rem;border-radius:8px;border:1px solid var(--success)">
      <strong>Assigned!</strong> Order #${data.assignment.order_id.slice(-6).toUpperCase()} → Delivery agent
    </div>`;
  } else {
    el.innerHTML = `<p class="text-muted text-sm">No orders in queue.</p>`;
  }
}

function showTab(name) {
  ['overview','orders','users','rests'].forEach(t => {
    document.getElementById('pane-'+t).style.display = t===name ? 'block':'none';
    document.getElementById('tab-'+t).classList.toggle('active', t===name);
  });
  if (name==='users') loadUsersTab();
  if (name==='rests') loadRestsTab();
  if (name==='orders') loadOrdersTab();
}

// Auto-refresh every 30s
loadAnalytics();
setInterval(loadAnalytics, 30000);
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
