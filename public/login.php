<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Login';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/AuthController.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthController();
    $type = $_POST['type'] ?? 'user';
    if ($type === 'admin') {
        $result = $auth->loginAdmin($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) { header('Location: ' . BASE_URL . '/admin.php'); exit; }
    } elseif ($type === 'restaurant') {
        $result = $auth->loginRestaurant($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) { header('Location: ' . BASE_URL . '/restaurant_dashboard.php'); exit; }
    } else {
        $result = $auth->loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) { header('Location: ' . BASE_URL . '/index.php'); exit; }
    }
    $error = $result['error'] ?? 'Login failed';
}
require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="form-card">
  <h2> Welcome back</h2>
  <p class="sub">Sign in to order your favourite food</p>

  <?php if ($error): ?>
    <div style="background:rgba(226,55,68,.15);color:#e23744;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.875rem"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Login type tabs -->
  <div class="tabs" style="margin-bottom:1.5rem">
    <div class="tab active" onclick="setType('user',this)">Customer</div>
    <div class="tab" onclick="setType('restaurant',this)">Restaurant</div>
    <div class="tab" onclick="setType('admin',this)">Admin</div>
  </div>

  <form method="POST">
    <input type="hidden" name="type" id="loginType" value="user">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email']??'') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem">Sign In</button>
  </form>

  <div class="form-divider" style="margin:1.25rem 0">or</div>
  <p style="text-align:center;font-size:.875rem;color:var(--text-muted)">
    Don't have an account? <a href="/food_tracking_system/public/register.php">Register</a>
  </p>
  <p style="text-align:center;font-size:.875rem;color:var(--text-muted);margin-top:.5rem">
    Own a restaurant? <a href="/food_tracking_system/public/restaurant_register.php">Register restaurant</a>
  </p>
</div>
</main>
<script>
function setType(type, el) {
  document.getElementById('loginType').value = type;
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
