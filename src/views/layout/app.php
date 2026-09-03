<?php
/** @var string $content */
/** @var array|null $currentUser */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Hub</title>
<link rel="stylesheet" href="<?= e(base_url('assets/style.css')) ?>">
</head>
<body>
<?php if ($currentUser): ?>
<div class="topbar">
  <div class="brand">Client Hub</div>
  <nav>
    <a href="<?= e(base_url('clients')) ?>" class="<?= str_starts_with($path, '/clients') ? 'active' : '' ?>">Clients</a>
    <a href="<?= e(base_url('courses')) ?>" class="<?= str_starts_with($path, '/courses') ? 'active' : '' ?>">Courses</a>
    <?php if (Auth::isAA()): ?>
      <a href="<?= e(base_url('contracts/expiring')) ?>" class="<?= str_starts_with($path, '/contracts') ? 'active' : '' ?>">Contract review</a>
      <a href="<?= e(base_url('staff')) ?>" class="<?= str_starts_with($path, '/staff') ? 'active' : '' ?>">Staff</a>
    <?php endif; ?>
    <?php if (Access::canViewActivityLog()): ?>
      <a href="<?= e(base_url('activity')) ?>" class="<?= str_starts_with($path, '/activity') ? 'active' : '' ?>">Activity</a>
    <?php endif; ?>
  </nav>
  <div class="who">
    <span><?= e($currentUser['name']) ?> · <?= e(ucfirst($currentUser['role'] === 'aa' ? 'AA' : $currentUser['role'])) ?></span>
    <form class="inline" method="post" action="<?= e(base_url('logout')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Log out</button></form>
  </div>
</div>
<?php endif; ?>
<div class="container">
  <?php if ($msg = flash('success')): ?><div class="flash success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($msg = flash('error')): ?><div class="flash error"><?= e($msg) ?></div><?php endif; ?>
  <?= $content ?>
</div>
</body>
</html>
