<?php
declare(strict_types=1);

final class StaffController
{
    public static function index(): void
    {
        Auth::requireLogin();
        if (!Access::canViewStaffPage()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $staff = Db::pdo()->query(
            "SELECT * FROM users ORDER BY FIELD(role, 'aa', 'staff'), name"
        )->fetchAll();

        render('staff/index', [
            'staff' => $staff,
        ]);
    }

    public static function store(): void
    {
        Auth::requireRole('aa');

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = 'staff'; // the only role this form can create — AA is seeded, not added here

        if ($name === '' || $email === '') {
            flash('error', 'Name and email are required.');
            redirect('/staff');
        }

        // Temporary password — the account is forced to reset it on first login.
        $tempPassword = bin2hex(random_bytes(8));

        $stmt = Db::pdo()->prepare(
            'INSERT INTO users (name, email, password_hash, role, status, must_reset_password)
             VALUES (:name, :email, :hash, :role, \'active\', 1)'
        );
        try {
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'hash' => password_hash($tempPassword, PASSWORD_DEFAULT),
                'role' => $role,
            ]);
        } catch (PDOException $e) {
            // Only a real duplicate-email violation gets the friendly message —
            // any other DB error (e.g. `role` column not yet migrated to accept
            // 'staff') surfaces as-is so it doesn't get misdiagnosed as this.
            if ($e->getCode() === '23000') {
                flash('error', 'That email is already in use.');
                redirect('/staff');
            }
            flash('error', 'Could not create the account: ' . $e->getMessage());
            redirect('/staff');
        }

        $userId = (int) Db::pdo()->lastInsertId();
        Activity::log('staff.create', 'user', $userId);

        flash('success', "$name added as $role. Temporary password: $tempPassword — share it securely, they'll be forced to change it on first login.");
        redirect('/staff');
    }

    public static function toggleStatus(array $routeParams): void
    {
        Auth::requireRole('aa');
        $userId = (int) $routeParams['id'];

        $stmt = Db::pdo()->prepare('SELECT status, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            flash('error', 'Account not found.');
            redirect('/staff');
        }
        if ($user['role'] === 'aa') {
            flash('error', "The AA account can't be disabled from here.");
            redirect('/staff');
        }

        $newStatus = $user['status'] === 'active' ? 'disabled' : 'active';
        $upd = Db::pdo()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $upd->execute(['status' => $newStatus, 'id' => $userId]);
        Activity::log('staff.status_change', 'user', $userId, $newStatus);

        flash('success', 'Account ' . $newStatus . '.');
        redirect('/staff');
    }

    /**
     * Permanently removes a staff account. Who's allowed depends on the
     * target's role — see Access::canDeleteStaff(). Blocked (with a
     * friendlier message than a raw DB error) if the account has activity
     * on record — contracts, course records, or logged sessions all
     * reference who created them, and MySQL's foreign keys refuse the
     * delete rather than silently orphaning that history.
     */
    public static function destroy(array $routeParams): void
    {
        Auth::requireLogin();
        $userId = (int) $routeParams['id'];

        $stmt = Db::pdo()->prepare('SELECT id, name, role FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'Account not found.');
            redirect('/staff');
        }

        if ($userId === Auth::id()) {
            flash('error', "You can't remove your own account.");
            redirect('/staff');
        }

        if (!Access::canDeleteStaff($target['role'])) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        try {
            $del = Db::pdo()->prepare('DELETE FROM users WHERE id = :id');
            $del->execute(['id' => $userId]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                flash('error', $target['name'] . " has activity on record (logged sessions, course records, or contracts) and can't be permanently deleted — disable their account instead to revoke access without losing that history.");
                redirect('/staff');
            }
            throw $e;
        }

        Activity::log('staff.delete', 'user', $userId, $target['role']);
        flash('success', $target['name'] . ' removed.');
        redirect('/staff');
    }
}
