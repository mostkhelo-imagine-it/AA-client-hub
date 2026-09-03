<?php
/** @var string $token */
/** @var array $header */
/** @var array $previewRows */
/** @var int $totalRows */
/** @var array $guess */
?>
<h1>Match columns</h1>
<p class="muted"><?= e((string) $totalRows) ?> rows found. Best-guess matches are pre-filled from your column headers — check each one, especially anything left on "don't import".</p>

<div class="card">
  <h2 style="margin-top:0;">Preview</h2>
  <div style="overflow-x:auto;">
    <table>
      <thead><tr><?php foreach ($header as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <?php foreach ($previewRows as $row): ?>
          <tr><?php foreach ($header as $col): ?><td><?= e((string) ($row[$col] ?? '')) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" action="<?= e(base_url('clients/import/commit')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">

  <div class="card">
    <h2 style="margin-top:0;">Name</h2>
    <p class="muted">Use one full-name column, or first + last name separately — whichever the file has.</p>
    <label for="map_full_name">Full name column</label>
    <?php import_select($header, 'map[full_name]', $guess['full_name']); ?>
    <div class="grid-2">
      <div>
        <label for="map_first_name">First name column</label>
        <?php import_select($header, 'map[first_name]', $guess['first_name']); ?>
      </div>
      <div>
        <label for="map_last_name">Last name column</label>
        <?php import_select($header, 'map[last_name]', $guess['last_name']); ?>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">Contact fields</h2>
    <div class="grid-2">
      <div><label for="map_email">Email column</label><?php import_select($header, 'map[email]', $guess['email']); ?></div>
      <div><label for="map_phone">Phone column</label><?php import_select($header, 'map[phone]', $guess['phone']); ?></div>
    </div>
    <div class="grid-2">
      <div><label for="map_address">Address column</label><?php import_select($header, 'map[address]', $guess['address']); ?></div>
      <div><label for="map_source">Source column</label><?php import_select($header, 'map[source]', $guess['source']); ?></div>
    </div>
    <label for="map_notes">Notes column</label>
    <?php import_select($header, 'map[notes]', $guess['notes']); ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">Tier</h2>
    <label for="default_tier">Default tier (applied when nothing below matches)</label>
    <select id="default_tier" name="default_tier">
      <option value="basic">Basic</option>
      <option value="premium">Premium</option>
      <option value="reality_creator">Reality Creator</option>
    </select>

    <p class="muted" style="margin-top:14px;">Optional: if the file has a tags/labels column, match keywords in it to set tier automatically.</p>
    <label for="map_tags">Tags column</label>
    <?php import_select($header, 'map[tags]', $guess['tags']); ?>
    <div class="grid-2">
      <div>
        <label for="premium_keyword">Text that means Premium</label>
        <input id="premium_keyword" name="premium_keyword" placeholder="e.g. premium, متميز">
      </div>
      <div>
        <label for="reality_creator_keyword">Text that hints at Reality Creator</label>
        <input id="reality_creator_keyword" name="reality_creator_keyword" placeholder="e.g. 1-on-1, program name">
      </div>
    </div>
    <label style="display:flex; align-items:center; gap:8px; margin-top:10px; text-transform:none; letter-spacing:normal;">
      <input type="checkbox" name="auto_promote_reality_creator" value="1" style="width:auto;">
      Automatically set Reality Creator tier on a match — off by default, since a tag or course purchase isn't the same as a signed 1-on-1 contract. Left unchecked, matches are only flagged for you to review after import.
    </label>
  </div>

  <div class="card">
    <label style="display:flex; align-items:center; gap:8px; text-transform:none; letter-spacing:normal;">
      <input type="checkbox" name="skip_duplicates" value="1" checked style="width:auto;">
      Skip rows whose email already exists in Client Hub
    </label>
  </div>

  <div class="actions">
    <button class="btn primary" type="submit">Import <?= e((string) $totalRows) ?> rows</button>
    <a class="btn" href="<?= e(base_url('clients/import')) ?>">Start over</a>
  </div>
</form>
