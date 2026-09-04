<?php
declare(strict_types=1);

/** Home page — a quick summary of what's in the database, nothing more. */
final class DashboardController
{
    public static function index(): void
    {
        Auth::requireLogin();

        $tierCounts = ['basic' => 0, 'premium' => 0, 'reality_creator' => 0];
        $rows = Db::pdo()->query('SELECT tier, COUNT(*) AS n FROM clients GROUP BY tier')->fetchAll();
        foreach ($rows as $row) {
            $tierCounts[$row['tier']] = (int) $row['n'];
        }
        $totalClients = array_sum($tierCounts);

        $staffCount = (int) Db::pdo()->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
        $courseCount = (int) Db::pdo()->query('SELECT COUNT(*) FROM courses')->fetchColumn();
        $courseRecordCount = (int) Db::pdo()->query('SELECT COUNT(*) FROM course_records')->fetchColumn();
        $sessionLogCount = (int) Db::pdo()->query('SELECT COUNT(*) FROM session_logs')->fetchColumn();

        $recentClients = Db::pdo()->query(
            'SELECT id, full_name, tier, created_at FROM clients ORDER BY created_at DESC LIMIT 5'
        )->fetchAll();

        $recentActivity = Activity::recentFor(null, 8);

        render('dashboard/index', [
            'totalClients' => $totalClients,
            'tierCounts' => $tierCounts,
            'staffCount' => $staffCount,
            'courseCount' => $courseCount,
            'courseRecordCount' => $courseRecordCount,
            'sessionLogCount' => $sessionLogCount,
            'recentClients' => $recentClients,
            'recentActivity' => $recentActivity,
        ]);
    }
}
