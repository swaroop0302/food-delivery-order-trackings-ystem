<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Register';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/AuthController.php';

$errors = []; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth   = new AuthController();
    $result = $auth->registerUser($_POST);
    if ($result['success']) {
        // Auto-login after register
        $r2 = $auth->loginUser($_POST['email'], $_POST['password']);
        if ($r2['success']) { header('Location: ' . BASE_URL . '/index.php'); exit; }
        $success = 'Registered! Please login.';
    } else {
        $errors = $result['errors'] ?? [];
    }
}
require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="form-card">
  <h2> Create account</h2>
  <p class="sub">Join thousands ordering with FoodRush</p>

  <?php if ($success): ?><div style="background:rgba(0,184,148,.15);color:#00b894;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem"><?= $success ?></div><?php endif; ?>

  <form method="POST" novalidate>
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="name" required placeholder="John Doe" value="<?= htmlspecialchars($_POST['name']??'') ?>">
      <?php if (!empty($errors['name'])): ?><div class="form-error"><?= $errors['name'] ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email']??'') ?>">
      <?php if (!empty($errors['email'])): ?><div class="form-error"><?= $errors['email'] ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input type="tel" name="phone" placeholder="+91 9876543210" value="<?= htmlspecialchars($_POST['phone']??'') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="Min 6 characters">
      <?php if (!empty($errors['password'])): ?><div class="form-error"><?= $errors['password'] ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm_password" required placeholder="Repeat password">
      <?php if (!empty($errors['confirm_password'])): ?><div class="form-error"><?= $errors['confirm_password'] ?></div><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block" style="margin-top:.5rem">Create Account</button>
  </form>

  <div class="form-divider" style="margin:1.25rem 0">or</div>
  <p style="text-align:center;font-size:.875rem;color:var(--text-muted)">
    Already have an account? <a href="/food_tracking_system/public/login.php">Login</a>
  </p>
</div>
</main>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
