<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'Register Restaurant';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/AuthController.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth   = new AuthController();
    $result = $auth->registerRestaurant($_POST);
    if ($result['success']) {
        $r2 = $auth->loginRestaurant($_POST['email'], $_POST['password']);
        if ($r2['success']) { header('Location: ' . BASE_URL . '/restaurant_dashboard.php'); exit; }
    } else {
        $errors = $result['errors'] ?? [];
    }
}
require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="form-card" style="max-width:560px">
  <h2> Register Restaurant</h2>
  <p class="sub">List your restaurant on FoodRush</p>

  <form method="POST" novalidate>
    <div class="grid-2" style="gap:.75rem">
      <div class="form-group">
        <label>Restaurant Name</label>
        <input type="text" name="name" required placeholder="Spice Garden" value="<?= htmlspecialchars($_POST['name']??'') ?>">
        <?php if (!empty($errors['name'])): ?><div class="form-error"><?= $errors['name'] ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label>City</label>
        <input type="text" name="city" required placeholder="Mumbai" value="<?= htmlspecialchars($_POST['city']??'') ?>">
        <?php if (!empty($errors['city'])): ?><div class="form-error"><?= $errors['city'] ?></div><?php endif; ?>
      </div>
    </div>
    <div class="form-group">
      <label>Cuisine Types <small style="color:var(--text-muted)">(comma-separated)</small></label>
      <input type="text" name="cuisine" required placeholder="Indian, Chinese, Beverages" value="<?= htmlspecialchars($_POST['cuisine']??'') ?>">
      <?php if (!empty($errors['cuisine'])): ?><div class="form-error"><?= $errors['cuisine'] ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Address</label>
      <input type="text" name="address" placeholder="123 Main Street" value="<?= htmlspecialchars($_POST['address']??'') ?>">
    </div>
    <div class="grid-2" style="gap:.75rem">
      <div class="form-group">
        <label>Delivery Fee (₹)</label>
        <input type="number" name="delivery_fee" placeholder="30" value="<?= htmlspecialchars($_POST['delivery_fee']??'30') ?>">
      </div>
      <div class="form-group">
        <label>Min Order (₹)</label>
        <input type="number" name="min_order" placeholder="100" value="<?= htmlspecialchars($_POST['min_order']??'100') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Type</label>
      <select name="is_veg">
        <option value="0">Non-Vegetarian</option>
        <option value="1">Pure Vegetarian</option>
      </select>
    </div>
    <div class="form-group">
      <label>Login Email</label>
      <input type="email" name="email" required placeholder="owner@restaurant.com" value="<?= htmlspecialchars($_POST['email']??'') ?>">
      <?php if (!empty($errors['email'])): ?><div class="form-error"><?= $errors['email'] ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="Min 6 characters">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Register Restaurant</button>
  </form>
</div>
</main>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
