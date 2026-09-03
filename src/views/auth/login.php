<?php /** @var string|null $error */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in — Client Hub</title>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>Client Hub</h1>
    <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_url('login')) ?>">
      <?= csrf_field() ?>
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required autofocus>
      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>
      <div class="actions"><button class="btn primary" type="submit" style="width:100%">Log in</button></div>
    </form>
  </div>
</div>
</body>
</html>
