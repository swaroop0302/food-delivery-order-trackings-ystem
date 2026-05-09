/* ====================================================
   app.js — FoodRush core JS: cart, API calls, toasts
   ==================================================== */

const API = '/food_tracking_system/public/api.php?route=';

/* ── Cart State ───────────────────────────────────────── */
let cart = JSON.parse(localStorage.getItem('fr_cart') || '{"restaurant_id":"","restaurant_name":"","items":[]}');

function saveCart() {
  localStorage.setItem('fr_cart', JSON.stringify(cart));
  renderCart();
  updateCartBadge();
}

function addToCart(item, restaurantId, restaurantName) {
  if (cart.restaurant_id && cart.restaurant_id !== restaurantId) {
    if (!confirm('Your cart has items from another restaurant. Clear and start new?')) return;
    cart = { restaurant_id: '', restaurant_name: '', items: [] };
  }
  cart.restaurant_id   = restaurantId;
  cart.restaurant_name = restaurantName;
  const existing = cart.items.find(i => i.item_id === item.item_id);
  if (existing) existing.quantity++;
  else cart.items.push({ ...item, quantity: 1 });
  saveCart();
  showToast(`${item.name} added to cart`, 'success');
}

function removeFromCart(itemId) {
  cart.items = cart.items.filter(i => i.item_id !== itemId);
  if (!cart.items.length) cart = { restaurant_id: '', restaurant_name: '', items: [] };
  saveCart();
}

function updateQty(itemId, delta) {
  const item = cart.items.find(i => i.item_id === itemId);
  if (!item) return;
  item.quantity += delta;
  if (item.quantity <= 0) removeFromCart(itemId);
  else saveCart();
}

function getCartTotal() {
  return cart.items.reduce((s, i) => s + i.price * i.quantity, 0);
}

function getCartCount() {
  return cart.items.reduce((s, i) => s + i.quantity, 0);
}

function clearCart() {
  cart = { restaurant_id: '', restaurant_name: '', items: [] };
  saveCart();
}

/* ── Cart Sidebar ────────────────────────────────────── */
function renderCart() {
  const body = document.getElementById('cartItems');
  const foot = document.getElementById('cartFooter');
  if (!body) return;

  if (!cart.items.length) {
    body.innerHTML = '<div class="empty"><div class="empty-icon"></div><h3>Cart is empty</h3></div>';
    if (foot) foot.innerHTML = '';
    return;
  }

  body.innerHTML = cart.items.map(i => `
    <div class="cart-item">
      <div>
        <div class="ci-name">${escHtml(i.name)}</div>
        <div class="text-sm text-muted">₹${i.price}</div>
      </div>
      <div class="qty-ctrl">
        <button onclick="updateQty('${i.item_id}',-1)">−</button>
        <span>${i.quantity}</span>
        <button onclick="updateQty('${i.item_id}',1)">+</button>
      </div>
      <div class="ci-price">₹${(i.price * i.quantity).toFixed(2)}</div>
    </div>`).join('');

  if (foot) foot.innerHTML = `
    <div class="cart-total"><span>Total</span><span>₹${getCartTotal().toFixed(2)}</span></div>
    <button class="btn btn-primary btn-block" onclick="goCheckout()">Proceed to Checkout</button>`;
}

function toggleCart(open) {
  document.getElementById('cartSidebar')?.classList.toggle('open', open);
  document.getElementById('cartOverlay')?.classList.toggle('open', open);
}

function goCheckout() {
  if (!cart.items.length) return showToast('Cart is empty', 'error');
  window.location.href = '/food_tracking_system/public/checkout.php';
}

function updateCartBadge() {
  const badge = document.getElementById('cartBadge');
  if (badge) { const n = getCartCount(); badge.textContent = n; badge.style.display = n ? 'flex' : 'none'; }
}

/* ── API helpers ─────────────────────────────────────── */
async function apiFetch(route, options = {}, timeoutMs = 15000) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(API + route, {
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      signal: controller.signal,
      ...options
    });
    clearTimeout(timer);
    return await res.json();
  } catch (e) {
    clearTimeout(timer);
    if (e.name === 'AbortError') {
      console.error('API timeout', route);
      return { success: false, error: 'Request timed out. Please try again.' };
    }
    console.error('API error', e);
    return { success: false, error: 'Network error' };
  }
}

/* ── Toast ────────────────────────────────────────────── */
function showToast(msg, type = 'info', duration = 3000) {
  let tc = document.getElementById('toastContainer');
  if (!tc) { tc = document.createElement('div'); tc.id = 'toastContainer'; tc.className = 'toast-container'; document.body.appendChild(tc); }
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icons = { success: '', error: '', info: 'ℹ' };
  t.innerHTML = `<span>${icons[type] || 'ℹ'}</span><span>${escHtml(msg)}</span>`;
  tc.appendChild(t);
  setTimeout(() => { t.style.animation = 'fadeOut .3s ease forwards'; setTimeout(() => t.remove(), 300); }, duration);
}

/* ── Utility ──────────────────────────────────────────── */
function escHtml(s) {
  const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
}

function formatCurrency(n) { return '₹' + Number(n).toFixed(2); }

function timeAgo(isoStr) {
  if (!isoStr) return '';
  const diff = (Date.now() - new Date(isoStr)) / 1000;
  if (diff < 60) return 'Just now';
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
  return new Date(isoStr).toLocaleDateString();
}

function statusLabel(s) {
  const labels = { placed:'Order Placed', accepted:'Accepted', preparing:'Preparing', out_for_delivery:'On the way', delivered:'Delivered', cancelled:'Cancelled' };
  return labels[s] || s;
}

/* ── Init ────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  renderCart();
  updateCartBadge();

  // Cart toggle
  document.getElementById('cartBtn')?.addEventListener('click', () => toggleCart(true));
  document.getElementById('cartOverlay')?.addEventListener('click', () => toggleCart(false));
  document.getElementById('cartClose')?.addEventListener('click', () => toggleCart(false));

  // Nav mobile toggle
  document.getElementById('navToggle')?.addEventListener('click', () => {
    document.getElementById('navLinks')?.classList.toggle('open');
  });
});
