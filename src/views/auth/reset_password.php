<?php /** @var string|null $error */ ?>
<h1>Set a new password</h1>
<div class="card" style="max-width:420px;">
  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(base_url('reset-password')) ?>">
    <?= csrf_field() ?>
    <label for="password">New password (10+ characters)</label>
    <input id="password" type="password" name="password" minlength="10" required>
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" minlength="10" required>
    <div class="actions"><button class="btn primary" type="submit">Save password</button></div>
  </form>
</div>
