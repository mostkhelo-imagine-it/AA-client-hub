<?php
/** @var int $imported */
/** @var int $skippedDuplicate */
/** @var int $skippedBlank */
/** @var array $flaggedForReview */
?>
<h1>Import complete</h1>

<div class="card">
  <table>
    <tbody>
      <tr><td>Imported</td><td><?= e((string) $imported) ?></td></tr>
      <tr><td>Skipped — email already existed</td><td><?= e((string) $skippedDuplicate) ?></td></tr>
      <tr><td>Skipped — no name or email</td><td><?= e((string) $skippedBlank) ?></td></tr>
    </tbody>
  </table>
</div>

<?php if ($flaggedForReview): ?>
<div class="card">
  <h2 style="margin-top:0;">Worth a look before calling them Reality Creator</h2>
  <p class="muted">These matched your Reality Creator keyword but weren't auto-promoted — a tag or course purchase isn't the same as a signed 1-on-1 contract. Check each one and set their tier from their profile if it's warranted.</p>
  <table>
    <thead><tr><th>Name</th><th>Email</th></tr></thead>
    <tbody>
      <?php foreach ($flaggedForReview as $r): ?>
        <tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="actions">
  <a class="btn primary" href="<?= e(base_url('clients')) ?>">Go to clients</a>
  <a class="btn" href="<?= e(base_url('clients/import')) ?>">Import another file</a>
</div>
