<?php /** @var array $expired */ ?>
<h1>Contract expiry review</h1>
<p class="muted">A Reality Creator contract whose end date has passed with no renewal on file lands here. Nothing changes for the client until you decide.</p>

<?php if (!$expired): ?>
  <p class="empty">Nothing waiting on a decision right now.</p>
<?php else: ?>
  <?php foreach ($expired as $c): ?>
    <div class="card">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <strong><a href="<?= e(base_url('clients/' . $c['client_id'])) ?>"><?= e($c['full_name']) ?></a></strong>
          <div class="muted">Contract ran <?= e($c['start_date']) ?> → <?= e($c['end_date']) ?> (ended <?= e((string) (int) ((strtotime('today') - strtotime($c['end_date'])) / 86400)) ?> days ago)</div>
        </div>
        <div style="display:flex; gap:10px;">
          <form method="post" action="<?= e(base_url('contracts/' . $c['id'] . '/decide')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="renew">
            <button class="btn primary" type="submit">Renew…</button>
          </form>
          <form method="post" action="<?= e(base_url('contracts/' . $c['id'] . '/decide')) ?>" onsubmit="return confirm('Move ' + <?= json_encode($c['full_name']) ?> + ' to Basic? Their session history stays on file.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="drop_to_basic">
            <button class="btn danger" type="submit">Drop to Basic</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
