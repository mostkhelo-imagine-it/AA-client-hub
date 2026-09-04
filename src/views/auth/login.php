<?php /** @var string|null $error */ ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in — AA New Reality - Client DB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Lora:wght@300;400;500&display=swap">
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1>AA New Reality - Client DB</h1>
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
