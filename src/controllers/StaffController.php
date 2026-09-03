<?php
declare(strict_types=1);

final class StaffController
{
    public static function index(): void
    {
        Auth::requireRole('aa');

        $staff = Db::pdo()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
        $clients = Db::pdo()->query('SELECT id, full_name, tier FROM clients ORDER BY full_name')->fetchAll();
        $assignments = Db::pdo()->query(
            'SELECT ca.*, u.name AS user_name, c.full_name AS client_name
             FROM client_assignments ca
             JOIN users u ON u.id = ca.user_id
             JOIN clients c ON c.id = ca.client_id
             ORDER BY u.name, c.full_name'
        )->fetchAll();

        render('staff/index', [
            'staff' => $staff,
            'clients' => $clients,
            'assignments' => $assignments,
        ]);
    }

    public static function store(): void
    {
        Auth::requireRole('aa');

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');

        if ($name === '' || $email === '' || !in_array($role, ['admin', 'assistant'], true)) {
            flash('error', 'Name, email, and a valid role are required.');
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
            flash('error', 'That email is already in use.');
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

    public static function assign(): void
    {
        Auth::requireRole('aa');

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($clientId <= 0 || $userId <= 0) {
            flash('error', 'Choose both a client and a staff member.');
            redirect('/staff');
        }

        $stmt = Db::pdo()->prepare(
            'INSERT IGNORE INTO client_assignments (client_id, user_id, assigned_by) VALUES (:c, :u, :by)'
        );
        $stmt->execute(['c' => $clientId, 'u' => $userId, 'by' => Auth::id()]);
        Activity::log('assignment.create', 'client', $clientId, "user #$userId");

        flash('success', 'Assignment added.');
        redirect('/staff');
    }

    public static function unassign(array $routeParams): void
    {
        Auth::requireRole('aa');
        $id = (int) $routeParams['id'];

        $stmt = Db::pdo()->prepare('DELETE FROM client_assignments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Activity::log('assignment.delete', null, null, "assignment #$id");

        flash('success', 'Assignment removed.');
        redirect('/staff');
    }
}
