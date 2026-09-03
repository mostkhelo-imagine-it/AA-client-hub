<?php
declare(strict_types=1);

/** Writes to activity_log. Called from controllers on every meaningful action. */
final class Activity
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details)'
        );
        $stmt->execute([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recentFor(?int $userId, int $limit = 100): array
    {
        if ($userId === null) {
            $stmt = Db::pdo()->prepare(
                'SELECT a.*, u.name AS user_name FROM activity_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.created_at DESC LIMIT :limit'
            );
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        } else {
            $stmt = Db::pdo()->prepare(
                'SELECT a.*, u.name AS user_name FROM activity_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.user_id = :user_id
                 ORDER BY a.created_at DESC LIMIT :limit'
            );
            $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
