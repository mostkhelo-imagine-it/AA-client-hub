<?php
/** @var array $clients */
/** @var string $search */
/** @var string $tier */
$tierLabel = ['basic' => 'Basic', 'premium' => 'Premium', 'reality_creator' => 'Reality Creator'];
?>
<div style="display:flex; justify-content:space-between; align-items:center;">
  <h1>Clients</h1>
  <a class="btn primary" href="<?= e(base_url('clients/new')) ?>">Add client</a>
</div>

<form class="filters" method="get" action="<?= e(base_url('clients')) ?>">
  <input type="text" name="q" placeholder="Search name, email, phone" value="<?= e($search) ?>">
  <select name="tier">
    <option value="">All tiers</option>
    <?php foreach ($tierLabel as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= $tier === $value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Filter</button>
</form>

<?php if (!$clients): ?>
  <p class="empty">No clients match.</p>
<?php else: ?>
<div class="card" style="padding:0;">
  <table>
    <thead><tr><th>Name</th><th>Tier</th><th>Email</th><th>Phone</th></tr></thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
        <tr>
          <td><a href="<?= e(base_url('clients/' . $c['id'])) ?>"><?= e($c['full_name']) ?></a></td>
          <td><span class="pill <?= e($c['tier']) ?>"><?= e($tierLabel[$c['tier']] ?? $c['tier']) ?></span></td>
          <td><?= e($c['email']) ?></td>
          <td><?= e($c['phone']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
