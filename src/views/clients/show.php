<?php
/** @var array $client */
/** @var array $courseRecords */
/** @var array $contracts */
/** @var array $sessionLogs */
/** @var array $courses */
/** @var bool $canViewSessionLogs */
/** @var bool $canLogSession */
/** @var bool $canDeleteSessionLog */
/** @var bool $canManageContracts */
/** @var bool $canLogCourseRecord */
$tierLabel = ['basic' => 'Basic', 'premium' => 'Premium', 'reality_creator' => 'Reality Creator'];
$activeContract = null;
foreach ($contracts as $c) {
    if ($c['status'] === 'active') { $activeContract = $c; break; }
}
?>
<div style="display:flex; justify-content:space-between; align-items:baseline;">
  <h1><?= e($client['full_name']) ?> <span class="pill <?= e($client['tier']) ?>"><?= e($tierLabel[$client['tier']] ?? $client['tier']) ?></span></h1>
  <a class="muted" href="<?= e(base_url('clients')) ?>">&larr; All clients</a>
</div>

<div class="card">
  <h2 style="margin-top:0;">Contact</h2>
  <div class="grid-2">
    <div><span class="muted">Email</span><br><?= e($client['email']) ?: '<span class="muted">—</span>' ?></div>
    <div><span class="muted">Phone</span><br><?= e($client['phone']) ?: '<span class="muted">—</span>' ?></div>
    <div><span class="muted">Address</span><br><?= e($client['address']) ?: '<span class="muted">—</span>' ?></div>
    <div><span class="muted">Source</span><br><?= e($client['source']) ?: '<span class="muted">—</span>' ?></div>
  </div>
  <?php if ($client['tier'] === 'premium'): ?>
    <p class="muted" style="margin-top:12px;">Subscription: <?= e($client['subscription_status'] ?? 'unknown') ?></p>
  <?php endif; ?>
  <?php if ($client['notes']): ?><p style="margin-top:12px;"><?= nl2br(e($client['notes'])) ?></p><?php endif; ?>
</div>

<?php if ($client['tier'] === 'reality_creator'): ?>
<div class="card" id="new-contract">
  <h2 style="margin-top:0;">Contract</h2>
  <?php if ($contracts): ?>
    <table>
      <thead><tr><th>Start</th><th>End</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($contracts as $c): ?>
          <tr><td><?= e($c['start_date']) ?></td><td><?= e($c['end_date']) ?></td><td><?= e(ucfirst(str_replace('_', ' ', $c['status']))) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="muted">No contract on file yet.</p>
  <?php endif; ?>

  <?php if ($canManageContracts): ?>
    <h2>Open / renew contract</h2>
    <form method="post" action="<?= e(base_url('clients/' . $client['id'] . '/contracts')) ?>">
      <?= csrf_field() ?>
      <?php if ($activeContract): ?>
        <input type="hidden" name="renewed_from" value="<?= e((string) $activeContract['id']) ?>">
        <p class="muted">This renews the current contract (started <?= e($activeContract['start_date']) ?>).</p>
      <?php endif; ?>
      <div class="grid-2">
        <div><label for="start_date">Start date</label><input id="start_date" type="date" name="start_date" required></div>
        <div><label for="end_date">End date</label><input id="end_date" type="date" name="end_date" required></div>
      </div>
      <div class="actions"><button class="btn primary" type="submit">Save contract</button></div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 style="margin-top:0;">Course history</h2>
  <?php if (!$courseRecords): ?>
    <p class="empty">No courses logged yet.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Course</th><th>Type</th><th>Date</th><th>Amount</th><th>Completion</th></tr></thead>
      <tbody>
        <?php foreach ($courseRecords as $r): ?>
          <tr>
            <td><?= e($r['course_title']) ?></td>
            <td><?= e(ucfirst($r['type'])) ?></td>
            <td><?= e($r['record_date']) ?></td>
            <td><?= $r['amount_paid'] !== null ? '$' . e(number_format((float) $r['amount_paid'], 2)) : '—' ?></td>
            <td><?= e($r['completion']) ?: '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($canLogCourseRecord): ?>
    <h2>Add a record</h2>
    <form method="post" action="<?= e(base_url('clients/' . $client['id'] . '/course-records')) ?>">
      <?= csrf_field() ?>
      <div class="grid-2">
        <div>
          <label for="course_id">Course</label>
          <select id="course_id" name="course_id" required>
            <option value="">Choose a course…</option>
            <?php foreach ($courses as $co): ?>
              <option value="<?= e((string) $co['id']) ?>"><?= e($co['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="type">Type</label>
          <select id="type" name="type">
            <option value="attended">Attended</option>
            <option value="purchased">Purchased</option>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div><label for="record_date">Date</label><input id="record_date" type="date" name="record_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div><label for="amount_paid">Amount paid</label><input id="amount_paid" name="amount_paid" placeholder="optional"></div>
      </div>
      <label for="completion">Completion</label>
      <input id="completion" name="completion" placeholder="e.g. 100%, in progress">
      <div class="actions"><button class="btn primary" type="submit">Add record</button></div>
    </form>
  <?php endif; ?>
</div>

<?php if ($client['tier'] === 'reality_creator' && $canViewSessionLogs): ?>
<div class="card">
  <h2 style="margin-top:0;">1-on-1 progress</h2>
  <?php if (!$sessionLogs): ?>
    <p class="empty">No sessions logged yet.</p>
  <?php else: ?>
    <?php foreach ($sessionLogs as $log): ?>
      <div style="border-bottom:1px solid var(--line); padding:12px 0;">
        <div style="display:flex; justify-content:space-between;">
          <strong><?= e($log['session_date']) ?></strong>
          <span class="muted">logged by <?= e($log['logged_by_name']) ?><?= $log['progress_rating'] ? ' · rating ' . e((string) $log['progress_rating']) . '/5' : '' ?></span>
        </div>
        <p style="margin:6px 0 4px;"><?= nl2br(e($log['summary'])) ?></p>
        <?php if ($log['goals_next']): ?><p class="muted">Next: <?= nl2br(e($log['goals_next'])) ?></p><?php endif; ?>
        <?php if ($canDeleteSessionLog): ?>
          <form class="inline" method="post" action="<?= e(base_url('clients/' . $client['id'] . '/sessions/' . $log['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this session entry permanently?');">
            <?= csrf_field() ?>
            <button class="btn danger" type="submit" style="margin-top:6px;">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($canLogSession): ?>
    <h2>Log a session</h2>
    <form method="post" action="<?= e(base_url('clients/' . $client['id'] . '/sessions')) ?>">
      <?= csrf_field() ?>
      <div class="grid-2">
        <div><label for="session_date">Date</label><input id="session_date" type="date" name="session_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div><label for="progress_rating">Progress rating (1–5, optional)</label><input id="progress_rating" name="progress_rating" type="number" min="1" max="5"></div>
      </div>
      <label for="summary">Summary</label>
      <textarea id="summary" name="summary" required></textarea>
      <label for="goals_next">Next goals</label>
      <textarea id="goals_next" name="goals_next"></textarea>
      <div class="actions"><button class="btn primary" type="submit">Log session</button></div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
