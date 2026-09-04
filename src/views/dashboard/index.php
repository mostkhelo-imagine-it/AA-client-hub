<?php
/** @var int $totalClients */
/** @var array $tierCounts */
/** @var int $staffCount */
/** @var int $courseCount */
/** @var int $courseRecordCount */
/** @var int $sessionLogCount */
/** @var array $recentClients */
/** @var array $recentActivity */
$tierLabel = ['basic' => 'Basic', 'premium' => 'Premium', 'reality_creator' => 'Reality Creator'];
?>
<h1>Overview</h1>

<div class="stat-grid">
  <div class="stat"><span class="num"><?= e((string) $totalClients) ?></span><span class="label">Clients</span></div>
  <div class="stat"><span class="num"><?= e((string) $tierCounts['basic']) ?></span><span class="label">Basic</span></div>
  <div class="stat"><span class="num"><?= e((string) $tierCounts['premium']) ?></span><span class="label">Premium</span></div>
  <div class="stat"><span class="num"><?= e((string) $tierCounts['reality_creator']) ?></span><span class="label">Reality Creator</span></div>
  <div class="stat"><span class="num"><?= e((string) $courseCount) ?></span><span class="label">Courses</span></div>
  <div class="stat"><span class="num"><?= e((string) $courseRecordCount) ?></span><span class="label">Course records</span></div>
  <div class="stat"><span class="num"><?= e((string) $sessionLogCount) ?></span><span class="label">Sessions logged</span></div>
  <div class="stat"><span class="num"><?= e((string) $staffCount) ?></span><span class="label">Active staff</span></div>
</div>

<div class="grid-2">
  <div class="card">
    <h2 style="margin-top:0;">Recently added clients</h2>
    <?php if (!$recentClients): ?>
      <p class="empty">No clients yet. <a href="<?= e(base_url('clients/new')) ?>">Add the first one</a>.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Name</th><th>Tier</th><th>Added</th></tr></thead>
        <tbody>
          <?php foreach ($recentClients as $c): ?>
            <tr>
              <td><a href="<?= e(base_url('clients/' . $c['id'])) ?>"><?= e($c['full_name']) ?></a></td>
              <td><span class="pill <?= e($c['tier']) ?>"><?= e($tierLabel[$c['tier']] ?? $c['tier']) ?></span></td>
              <td><?= e($c['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="margin-top:10px;"><a href="<?= e(base_url('clients')) ?>">All clients &rarr;</a></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">Recent activity</h2>
    <?php if (!$recentActivity): ?>
      <p class="empty">Nothing logged yet.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>When</th><th>Who</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($recentActivity as $a): ?>
            <tr>
              <td><?= e($a['created_at']) ?></td>
              <td><?= e($a['user_name'] ?? 'system') ?></td>
              <td><?= e($a['action']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p class="muted" style="margin-top:10px;"><a href="<?= e(base_url('activity')) ?>">Full activity log &rarr;</a></p>
    <?php endif; ?>
  </div>
</div>
