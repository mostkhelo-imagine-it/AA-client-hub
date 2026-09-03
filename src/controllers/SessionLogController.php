<?php
declare(strict_types=1);

final class SessionLogController
{
    public static function store(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];

        if (!Access::canLogSession()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $summary = trim((string) ($_POST['summary'] ?? ''));
        if ($summary === '') {
            flash('error', 'A session summary is required.');
            redirect('/clients/' . $clientId);
        }

        $rating = $_POST['progress_rating'] ?? '';
        $rating = ($rating !== '' && (int) $rating >= 1 && (int) $rating <= 5) ? (int) $rating : null;

        $stmt = Db::pdo()->prepare(
            'INSERT INTO session_logs (client_id, session_date, logged_by, summary, goals_next, progress_rating)
             VALUES (:client_id, :session_date, :logged_by, :summary, :goals_next, :rating)'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'session_date' => (string) ($_POST['session_date'] ?? date('Y-m-d')),
            'logged_by' => Auth::id(),
            'summary' => $summary,
            'goals_next' => trim((string) ($_POST['goals_next'] ?? '')) ?: null,
            'rating' => $rating,
        ]);
        Activity::log('session_log.create', 'client', $clientId);

        flash('success', 'Session logged.');
        redirect('/clients/' . $clientId);
    }

    public static function destroy(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];
        $logId = (int) $routeParams['logId'];

        if (!Access::canDeleteSessionLog()) {
            // Enforced here, server-side — not just a hidden delete button.
            http_response_code(403);
            render('errors/403');
            return;
        }

        $stmt = Db::pdo()->prepare('DELETE FROM session_logs WHERE id = :id AND client_id = :client_id');
        $stmt->execute(['id' => $logId, 'client_id' => $clientId]);
        Activity::log('session_log.delete', 'client', $clientId, "log #$logId");

        flash('success', 'Session entry deleted.');
        redirect('/clients/' . $clientId);
    }
}
