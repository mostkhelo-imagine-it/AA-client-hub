<?php
declare(strict_types=1);

/**
 * Session-based auth. Every role check happens here or in Access.php —
 * never trust a hidden button or a missing menu item as access control.
 */
final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        Activity::log('auth.login', 'user', (int) $user['id']);
        return true;
    }

    public static function logout(): void
    {
        if (self::user()) {
            Activity::log('auth.logout', 'user', self::user()['id']);
        }
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        static $cached = null;
        static $loaded = false;
        if ($loaded) {
            return $cached;
        }
        $loaded = true;
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $stmt = Db::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active') {
            return null;
        }
        $cached = $user;
        return $cached;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function role(): ?string
    {
        $user = self::user();
        return $user ? $user['role'] : null;
    }

    public static function isAA(): bool
    {
        return self::role() === 'aa';
    }

    public static function isStaff(): bool
    {
        return self::role() === 'staff';
    }

    public static function requireLogin(): void
    {
        if (!self::check() || !self::user()) {
            redirect('/login');
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            render('errors/403');
            exit;
        }
    }

    public static function setPassword(int $userId, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = Db::pdo()->prepare(
            'UPDATE users SET password_hash = :hash, must_reset_password = 0 WHERE id = :id'
        );
        $stmt->execute(['hash' => $hash, 'id' => $userId]);
    }
}
