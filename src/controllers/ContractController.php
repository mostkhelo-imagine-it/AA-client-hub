<?php
declare(strict_types=1);

final class ContractController
{
    /** AA/Admin: open a fresh contract for a Reality Creator client (first one, or a manual renewal). */
    public static function store(array $routeParams): void
    {
        Auth::requireRole('aa', 'admin');
        $clientId = (int) $routeParams['id'];

        $startDate = (string) ($_POST['start_date'] ?? date('Y-m-d'));
        $endDate = (string) ($_POST['end_date'] ?? '');
        if ($endDate === '' || $endDate <= $startDate) {
            flash('error', 'End date must be after the start date.');
            redirect('/clients/' . $clientId);
        }

        $renewedFrom = (int) ($_POST['renewed_from'] ?? 0) ?: null;

        Db::pdo()->beginTransaction();
        try {
            $stmt = Db::pdo()->prepare(
                'INSERT INTO contracts (client_id, start_date, end_date, status, renewed_from, created_by)
                 VALUES (:client_id, :start_date, :end_date, \'active\', :renewed_from, :created_by)'
            );
            $stmt->execute([
                'client_id' => $clientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'renewed_from' => $renewedFrom,
                'created_by' => Auth::id(),
            ]);

            if ($renewedFrom) {
                $upd = Db::pdo()->prepare("UPDATE contracts SET status = 'renewed' WHERE id = :id");
                $upd->execute(['id' => $renewedFrom]);
            }

            $tier = Db::pdo()->prepare("UPDATE clients SET tier = 'reality_creator' WHERE id = :id");
            $tier->execute(['id' => $clientId]);

            Db::pdo()->commit();
        } catch (Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }

        Activity::log('contract.create', 'client', $clientId);
        flash('success', 'Contract saved.');
        redirect('/clients/' . $clientId);
    }

    /**
     * AA only: contracts that ended with nothing renewed on file. This is
     * computed live (end_date has passed, status is still active/pending)
     * rather than relying on a cron flip — nothing here waits on a job that
     * might not be scheduled yet.
     */
    public static function expiryReview(): void
    {
        Auth::requireRole('aa');

        $stmt = Db::pdo()->query(
            'SELECT ct.*, cl.full_name, cl.id AS client_id
             FROM contracts ct
             JOIN clients cl ON cl.id = ct.client_id
             WHERE ct.status IN (\'active\', \'pending_decision\')
               AND ct.end_date < CURDATE()
               AND NOT EXISTS (
                   SELECT 1 FROM contracts nc WHERE nc.renewed_from = ct.id
               )
             ORDER BY ct.end_date ASC'
        );
        $expired = $stmt->fetchAll();

        render('contracts/expiry_review', ['expired' => $expired]);
    }

    /** AA decides: renew (redirect to the client to open a new contract) or drop to Basic. */
    public static function decide(array $routeParams): void
    {
        Auth::requireRole('aa');
        $contractId = (int) $routeParams['id'];
        $action = (string) ($_POST['action'] ?? '');

        $stmt = Db::pdo()->prepare('SELECT * FROM contracts WHERE id = :id');
        $stmt->execute(['id' => $contractId]);
        $contract = $stmt->fetch();
        if (!$contract) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        if ($action === 'drop_to_basic') {
            Db::pdo()->beginTransaction();
            try {
                $end = Db::pdo()->prepare("UPDATE contracts SET status = 'ended' WHERE id = :id");
                $end->execute(['id' => $contractId]);

                $tier = Db::pdo()->prepare(
                    "UPDATE clients SET tier = 'basic', subscription_status = NULL WHERE id = :id"
                );
                $tier->execute(['id' => $contract['client_id']]);
                Db::pdo()->commit();
            } catch (Throwable $e) {
                Db::pdo()->rollBack();
                throw $e;
            }
            Activity::log('contract.drop_to_basic', 'client', (int) $contract['client_id']);
            flash('success', 'Client moved to Basic. Session history stays on their profile.');
            redirect('/contracts/expiring');
        }

        if ($action === 'renew') {
            // Hand off to the client profile, where AA fills in the new dates.
            redirect('/clients/' . $contract['client_id'] . '#new-contract');
        }

        flash('error', 'Choose renew or drop to Basic.');
        redirect('/contracts/expiring');
    }
}
