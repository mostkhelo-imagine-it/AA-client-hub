<?php
/** @var array $staff */
$roleLabel = ['aa' => 'AA', 'staff' => 'Staff'];
$canManage = Access::canManageStaff(); // creating/disabling/removing accounts — AA only
?>
<h1>Staff</h1>

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
<p class="muted">Staff have the same access to client data as AA — the only thing reserved for AA is adding, disabling, or removing staff accounts. An account with logged sessions, course records, or contracts on file can't be permanently removed — disable it instead to revoke access without losing that history.</p>

<?php if ($canManage): ?>
<div class="card" style="max-width:480px;">
  <h2 style="margin-top:0;">Add staff account</h2>
  <form method="post" action="<?= e(base_url('staff')) ?>">
    <?= csrf_field() ?>
    <label for="name">Name</label>
    <input id="name" name="name" required>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" required>
    <div class="actions"><button class="btn primary" type="submit">Create account</button></div>
  </form>
</div>
<?php endif; ?>
