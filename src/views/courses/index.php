<?php
/** @var array $courses */
/** @var bool $canManage */
?>
<h1>Course catalog</h1>

<div class="card" style="padding:0;">
  <table>
    <thead><tr><th>Title</th><th>Type</th><th>Price</th><th>Platform</th><th>Attended</th><th>Purchased</th></tr></thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
        <tr>
          <td><?= e($c['title']) ?></td>
          <td><?= e(ucfirst($c['type'])) ?></td>
          <td><?= $c['price'] !== null ? '$' . e(number_format((float) $c['price'], 2)) : '—' ?></td>
          <td><?= e($c['platform']) ?: '—' ?></td>
          <td><?= e((string) $c['attended_count']) ?></td>
          <td><?= e((string) $c['purchased_count']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$courses): ?><tr><td colspan="6" class="empty">No courses yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($canManage): ?>
<div class="card" style="max-width:480px;">
  <h2 style="margin-top:0;">Add a course</h2>
  <form method="post" action="<?= e(base_url('courses')) ?>">
    <?= csrf_field() ?>
    <label for="title">Title</label>
    <input id="title" name="title" required>
    <div class="grid-2">
      <div>
        <label for="type">Type</label>
        <select id="type" name="type">
          <option value="online">Online</option>
          <option value="live">Live</option>
        </select>
      </div>
      <div><label for="price">Price</label><input id="price" name="price" placeholder="optional"></div>
    </div>
    <label for="platform">Platform</label>
    <input id="platform" name="platform" placeholder="e.g. self-hosted, FluentCommunity">
    <div class="actions"><button class="btn primary" type="submit">Add course</button></div>
  </form>
</div>
<?php endif; ?>
