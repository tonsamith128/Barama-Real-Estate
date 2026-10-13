<link rel="stylesheet" href="<?= e(asset('css/users-premium-v117.css') . '?v=117a') ?>">
<section class="workspace-page barama-user-create-v117">
  <div class="workspace-page__header"><div><span class="eyebrow">Admin · Users</span><h1>Add New User</h1><p>Create a real customer account. Email verification remains required before business access.</p></div><a class="btn btn-outline-secondary" href="<?= e(url('/dashboard/users')) ?>">Back to users</a></div>
  <div class="barama-user-create-v117__panel">
    <?php if ((string) ($formError ?? '') !== ''): ?><div class="alert-barama alert-barama--error" role="alert"><?= e((string) $formError) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/dashboard/users/create')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <label for="barama-user-name">Full name</label>
      <input id="barama-user-name" name="name" type="text" minlength="2" maxlength="120" autocomplete="off" required>
      <label for="barama-user-email">Email address</label>
      <input id="barama-user-email" name="email" type="email" maxlength="191" autocomplete="off" required>
      <label for="barama-user-password">Initial password</label>
      <input id="barama-user-password" name="password" type="password" autocomplete="new-password" minlength="8" required>
      <label for="barama-user-confirm">Confirm initial password</label>
      <input id="barama-user-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
      <p>Customer role only. The account is created unverified; other access is granted separately through approved workflows. Share initial credentials securely, not by email.</p>
      <div class="barama-user-create-v117__actions"><button type="submit" class="btn btn-primary">Create user</button><a href="<?= e(url('/dashboard/users')) ?>" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
  </div>
</section>
