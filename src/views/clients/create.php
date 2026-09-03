<h1>Add a client</h1>
<div class="card" style="max-width:520px;">
  <form method="post" action="<?= e(base_url('clients')) ?>">
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" required>
    <div class="grid-2">
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email">
      </div>
      <div>
        <label for="phone">Phone</label>
        <input id="phone" name="phone">
      </div>
    </div>
    <label for="address">Address</label>
    <input id="address" name="address">
    <div class="grid-2">
      <div>
        <label for="source">Source</label>
        <input id="source" name="source" placeholder="e.g. web, referral">
      </div>
      <div>
        <label for="tier">Tier</label>
        <select id="tier" name="tier">
          <option value="basic">Basic</option>
          <option value="premium">Premium (monthly subscriber)</option>
          <option value="reality_creator">Reality Creator (1-on-1)</option>
        </select>
      </div>
    </div>
    <label for="notes">Notes</label>
    <textarea id="notes" name="notes"></textarea>
    <div class="actions">
      <button class="btn primary" type="submit">Save client</button>
      <a class="btn" href="<?= e(base_url('clients')) ?>">Cancel</a>
    </div>
  </form>
</div>
