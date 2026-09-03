<?php /** @var array $client */ ?>
<h1>Edit <?= e($client['full_name']) ?></h1>
<div class="card" style="max-width:520px;">
  <form method="post" action="<?= e(base_url('clients/' . $client['id'])) ?>">
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" value="<?= e($client['full_name']) ?>" required>
    <div class="grid-2">
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($client['email']) ?>">
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= e($client['phone']) ?>">
      </div>
    </div>
    <label for="address">Address</label>
    <input id="address" name="address" value="<?= e($client['address']) ?>">
    <div class="grid-2">
      <div>
        <label for="source">Source</label>
        <input id="source" name="source" placeholder="e.g. web, referral" value="<?= e($client['source']) ?>">
      </div>
      <div>
        <label for="tier">Tier</label>
        <select id="tier" name="tier">
          <option value="basic" <?= $client['tier'] === 'basic' ? 'selected' : '' ?>>Basic</option>
          <option value="premium" <?= $client['tier'] === 'premium' ? 'selected' : '' ?>>Premium (monthly subscriber)</option>
          <option value="reality_creator" <?= $client['tier'] === 'reality_creator' ? 'selected' : '' ?>>Reality Creator (1-on-1)</option>
        </select>
      </div>
    </div>
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes"><?= e($client['notes']) ?></textarea>
    <div class="actions">
      <button class="btn primary" type="submit">Save changes</button>
      <a class="btn" href="<?= e(base_url('clients/' . $client['id'])) ?>">Cancel</a>
    </div>
  </form>
</div>
