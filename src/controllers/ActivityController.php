<?php
declare(strict_types=1);

final class ActivityController
{
    public static function index(): void
    {
        Auth::requireLogin();
        if (!Access::canViewActivityLog()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $entries = Activity::recentFor(Access::activityLogUserFilter(), 200);
        render('activity/index', ['entries' => $entries]);
    }
}
