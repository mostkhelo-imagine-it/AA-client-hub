<?php
declare(strict_types=1);

/**
 * Every access rule from the design spec's roles table lives here, in one
 * place, so a controller or a query never has to re-derive who's allowed
 * to see what. Nothing in views/ should be trusted to hide something the
 * database would still hand over on request.
 */
final class Access
{
    /** Can the current user see this client at all? */
    public static function canViewClient(int $clientId): bool
    {
        if (Auth::isFullAccess()) {
            return true; // AA + Super Admin + Admin see every client
        }
        if (Auth::isAssistant()) {
            return self::isAssigned($clientId, Auth::id());
        }
        return false;
    }

    /**
     * Editing or deleting an existing client record — AA and Super Admin only.
     * Admin can still add client data (create clients, log courses/sessions,
     * manage contracts/catalog below) but not change or remove what's there.
     */
    public static function canEditClient(): bool
    {
        return Auth::isAA() || Auth::isSuperAdmin();
    }

    public static function canDeleteClient(): bool
    {
        return Auth::isAA() || Auth::isSuperAdmin();
    }

    public static function isAssigned(int $clientId, int $userId): bool
    {
        $stmt = Db::pdo()->prepare(
            'SELECT 1 FROM client_assignments WHERE client_id = :c AND user_id = :u LIMIT 1'
        );
        $stmt->execute(['c' => $clientId, 'u' => $userId]);
        return (bool) $stmt->fetchColumn();
    }

    /** SQL fragment + params to scope a `clients c` query to what this user may see. */
    public static function clientScopeSql(): array
    {
        if (Auth::isFullAccess()) {
            return ['', []];
        }
        // Assistant: only assigned clients.
        return [
            'AND c.id IN (SELECT client_id FROM client_assignments WHERE user_id = :scope_user_id)',
            ['scope_user_id' => Auth::id()],
        ];
    }

    /** Reality Creator session progress: AA + Admin only, per the spec. */
    public static function canViewSessionLogs(): bool
    {
        return Auth::isFullAccess();
    }

    public static function canLogSession(): bool
    {
        return Auth::isFullAccess();
    }

    /** Deleting session history is AA-only — admins can add, never remove. */
    public static function canDeleteSessionLog(): bool
    {
        return Auth::isAA();
    }

    public static function canManageContracts(): bool
    {
        return Auth::isFullAccess();
    }

    public static function canManageCourseCatalog(): bool
    {
        return Auth::isFullAccess();
    }

    /** Assistants can log attendance/purchases only for clients assigned to them. */
    public static function canLogCourseRecord(int $clientId): bool
    {
        if (Auth::isFullAccess()) {
            return true;
        }
        if (Auth::isAssistant()) {
            return self::isAssigned($clientId, Auth::id());
        }
        return false;
    }

    /** Creating accounts and managing client assignments stays AA-only. */
    public static function canManageStaff(): bool
    {
        return Auth::isAA();
    }

    /** AA, Super Admin, and Admin all need the staff page — to remove different tiers below them. */
    public static function canViewStaffPage(): bool
    {
        return Auth::isFullAccess();
    }

    /**
     * Who can remove a given staff account, based on the target's role:
     * AA removes anyone but another AA; Super Admin removes Admin and
     * Assistant accounts; Admin removes Assistant accounts only.
     */
    public static function canDeleteStaff(string $targetRole): bool
    {
        if (Auth::isAA()) {
            return $targetRole !== 'aa';
        }
        if (Auth::isSuperAdmin()) {
            return in_array($targetRole, ['admin', 'assistant'], true);
        }
        if (Auth::isAdmin()) {
            return $targetRole === 'assistant';
        }
        return false;
    }

    /** Bulk CSV import touches every client — AA/Admin only, same as the course catalog. */
    public static function canImportClients(): bool
    {
        return Auth::isFullAccess();
    }

    public static function canViewActivityLog(): bool
    {
        return Auth::isFullAccess();
    }

    /** Admins only see their own actions in the activity log; AA and Super Admin see everyone's. */
    public static function activityLogUserFilter(): ?int
    {
        return Auth::isAdmin() ? Auth::id() : null;
    }

    public static function requireCanViewClient(int $clientId): void
    {
        if (!self::canViewClient($clientId)) {
            http_response_code(403);
            render('errors/403');
            exit;
        }
    }
}
