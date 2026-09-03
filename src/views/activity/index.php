<?php /** @var array $entries */ ?>
<h1>Activity log</h1>
<p class="muted"><?= Auth::isAA() ? 'Every action across the team.' : 'Your own actions.' ?></p>

<div class="card" style="padding:0;">
  <table>
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>On</th></tr></thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= e($e['created_at']) ?></td>
          <td><?= e($e['user_name'] ?? 'system') ?></td>
          <td><?= e($e['action']) ?></td>
          <td><?= e(trim(($e['entity_type'] ?? '') . ' ' . ($e['entity_id'] ?? ''))) ?><?= $e['details'] ? ' — ' . e($e['details']) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$entries): ?><tr><td colspan="4" class="empty">Nothing logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
