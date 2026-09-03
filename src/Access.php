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
    // Client data — clients, contracts, sessions, courses, import, activity
    // log — is equally open to AA and Staff. The only thing that stays
    // AA-only is managing staff accounts themselves (see canManageStaff /
    // canDeleteStaff below). Any logged-in user reaches this point via
    // Auth::requireLogin() in the controller, so these are simple "yes."

    public static function canEditClient(): bool
    {
        return true;
    }

    public static function canDeleteClient(): bool
    {
        return true;
    }

    public static function canViewSessionLogs(): bool
    {
        return true;
    }

    public static function canLogSession(): bool
    {
        return true;
    }

    public static function canDeleteSessionLog(): bool
    {
        return true;
    }

    public static function canManageContracts(): bool
    {
        return true;
    }

    public static function canManageCourseCatalog(): bool
    {
        return true;
    }

    public static function canLogCourseRecord(int $clientId): bool
    {
        return true;
    }

    /** Bulk CSV/Excel import touches every client — open to any logged-in user. */
    public static function canImportClients(): bool
    {
        return true;
    }

    public static function canViewActivityLog(): bool
    {
        return true;
    }

    /** No per-role filtering — everyone sees the full trail. */
    public static function activityLogUserFilter(): ?int
    {
        return null;
    }

    /** Creating, disabling, or removing staff accounts stays AA-only. */
    public static function canManageStaff(): bool
    {
        return Auth::isAA();
    }

    /** Staff can see the staff list (who has accounts); only AA can act on it. */
    public static function canViewStaffPage(): bool
    {
        return true;
    }

    /** Only AA can remove a staff account, and never the AA account itself. */
    public static function canDeleteStaff(string $targetRole): bool
    {
        return Auth::isAA() && $targetRole !== 'aa';
    }
}
