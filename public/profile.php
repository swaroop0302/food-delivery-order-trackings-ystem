<?php
define('ROOT_DIR', dirname(__DIR__));
$pageTitle = 'My Profile';
require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/controllers/SessionController.php';
require_once ROOT_DIR . '/models/UserModel.php';

$sess   = new SessionController();
$user   = $sess->requireLogin();
$model  = new UserModel();
$profile= $model->findById($user['id']);
$msg    = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $model->update($user['id'], ['name' => $_POST['name'], 'phone' => $_POST['phone']]);
        $msg = 'Profile updated!';
    } elseif ($action === 'add_address') {
        $model->addAddress($user['id'], ['street'=>$_POST['street'],'city'=>$_POST['city'],'pincode'=>$_POST['pincode'],'landmark'=>$_POST['landmark']??'']);
        $msg = 'Address added!';
    }
    $profile = $model->findById($user['id']);
}
require_once ROOT_DIR . '/views/partials/header.php';
?>
<main>
<div class="container" style="padding:2rem 0 4rem;max-width:800px">
  <div class="page-header"><h1> My Profile</h1></div>
  <?php if ($msg): ?><div style="background:rgba(0,184,148,.15);color:#00b894;padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <div class="grid-2" style="align-items:start">
    <!-- Profile info -->
    <div class="card card-body">
      <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Basic Info</h3>
      <form method="POST">
        <input type="hidden" name="action" value="profile">
        <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($profile['name']??'') ?>" required></div>
        <div class="form-group"><label>Email</label><input type="email" value="<?= htmlspecialchars($profile['email']??'') ?>" disabled></div>
        <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($profile['phone']??'') ?>"></div>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
      </form>
    </div>

    <!-- Addresses -->
    <div>
      <div class="card card-body" style="margin-bottom:1rem">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem"> Saved Addresses</h3>
        <?php if (!empty($profile['addresses'])): ?>
          <?php foreach ($profile['addresses'] as $addr): ?>
            <div style="padding:.75rem;background:var(--dark-2);border-radius:8px;margin-bottom:.5rem;position:relative">
              <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($addr['street']??'') ?></div>
              <div style="font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars(($addr['city']??'').', '.($addr['pincode']??'')) ?></div>
              <button onclick="deleteAddress('<?= $addr['_id'] ?>')" style="position:absolute;top:.5rem;right:.5rem;background:none;border:none;color:var(--danger);cursor:pointer;font-size:.8rem"></button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted text-sm">No saved addresses yet.</p>
        <?php endif; ?>
      </div>
      <div class="card card-body">
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">+ Add New Address</h3>
        <form method="POST">
          <input type="hidden" name="action" value="add_address">
          <div class="form-group"><label>Street</label><input type="text" name="street" required placeholder="123 Main Street"></div>
          <div class="grid-2" style="gap:.5rem">
            <div class="form-group"><label>City</label><input type="text" name="city" required></div>
            <div class="form-group"><label>Pincode</label><input type="text" name="pincode" required></div>
          </div>
          <div class="form-group"><label>Landmark</label><input type="text" name="landmark" placeholder="Optional"></div>
          <button type="submit" class="btn btn-outline btn-sm">Add Address</button>
        </form>
      </div>
    </div>
  </div>
</div>
</main>
<script>
async function deleteAddress(addrId) {
  if (!confirm('Remove this address?')) return;
  const data = await apiFetch('user/address/' + addrId, { method:'DELETE' });
  if (data.success) { showToast('Address removed','success'); setTimeout(()=>location.reload(),800); }
  else showToast('Failed','error');
}
</script>
<?php require_once ROOT_DIR . '/views/partials/footer.php'; ?>
