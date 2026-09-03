<?php
/** @var array $staff */
/** @var array $clients */
/** @var array $assignments */
$roleLabel = ['aa' => 'AA', 'super_admin' => 'Super Admin', 'admin' => 'Admin', 'assistant' => 'Assistant'];
$canManage = Access::canManageStaff(); // account creation + assignments — AA only
?>
<h1>Staff & assignments</h1>

<div class="card" style="padding:0;">
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($staff as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($roleLabel[$u['role']] ?? $u['role']) ?></td>
          <td><?= e(ucfirst($u['status'])) ?></td>
          <td style="white-space:nowrap;">
            <?php if ($u['role'] !== 'aa' && $canManage): ?>
              <form class="inline" method="post" action="<?= e(base_url('staff/' . $u['id'] . '/toggle-status')) ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit"><?= $u['status'] === 'active' ? 'Disable' : 'Re-enable' ?></button>
              </form>
            <?php endif; ?>
            <?php if (Access::canDeleteStaff($u['role'])): ?>
              <form class="inline" method="post" action="<?= e(base_url('staff/' . $u['id'] . '/delete')) ?>" onsubmit="return confirm('Permanently remove ' + <?= json_encode($u['name']) ?> + '? This can\'t be undone.');">
                <?= csrf_field() ?>
                <button class="btn danger" type="submit">Remove</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="muted">Removal depends on your role: AA removes anyone; Super Admin removes Admin and Assistant accounts; Admin removes Assistant accounts only. An account with logged sessions, course records, or contracts on file can't be permanently removed — disable it instead to revoke access without losing that history.</p>

<?php if ($canManage): ?>
<div class="card" style="max-width:480px;">
  <h2 style="margin-top:0;">Add staff account</h2>
  <form method="post" action="<?= e(base_url('staff')) ?>">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input id="name" name="name" required>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required>
    <label for="role">Role</label>
    <select id="role" name="role">
      <option value="super_admin">Super Admin</option>
      <option value="admin">Admin</option>
      <option value="assistant">Assistant</option>
    </select>
    <div class="actions"><button class="btn primary" type="submit">Create account</button></div>
  </form>
</div>

<div class="card">
  <h2 style="margin-top:0;">Client assignments</h2>
  <p class="muted">Assistants only see clients assigned to them here. AA, Super Admin, and Admin see everyone by default.</p>
  <table>
    <thead><tr><th>Staff</th><th>Client</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= e($a['user_name']) ?></td>
          <td><?= e($a['client_name']) ?></td>
          <td>
            <form class="inline" method="post" action="<?= e(base_url('staff/assignments/' . $a['id'] . '/delete')) ?>">
              <?= csrf_field() ?>
              <button class="btn danger" type="submit">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$assignments): ?><tr><td colspan="3" class="empty">No assignments yet.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <h2>Add an assignment</h2>
  <form method="post" action="<?= e(base_url('staff/assignments')) ?>">
    <?= csrf_field() ?>
    <div class="grid-2">
      <div>
        <label for="user_id">Staff member</label>
        <select id="user_id" name="user_id" required>
          <option value="">Choose…</option>
          <?php foreach ($staff as $u): ?>
            <?php if ($u['role'] !== 'aa'): ?>
              <option value="<?= e((string) $u['id']) ?>"><?= e($u['name']) ?> (<?= e($roleLabel[$u['role']] ?? $u['role']) ?>)</option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="client_id">Client</label>
        <select id="client_id" name="client_id" required>
          <option value="">Choose…</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= e((string) $c['id']) ?>"><?= e($c['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="actions"><button class="btn primary" type="submit">Assign</button></div>
  </form>
</div>
<?php endif; ?>
