<?php
declare(strict_types=1);

final class ClientController
{
    public static function index(): void
    {
        Auth::requireLogin();

        $search = trim((string) ($_GET['q'] ?? ''));
        $tier = (string) ($_GET['tier'] ?? '');

        [$scopeSql, $scopeParams] = Access::clientScopeSql();

        $where = ['1=1'];
        $params = $scopeParams;

        if ($search !== '') {
            // Real (non-emulated) prepares can't reuse one named placeholder more than
            // once per query, so each LIKE gets its own — all bound to the same value.
            $where[] = '(c.full_name LIKE :q1 OR c.email LIKE :q2 OR c.phone LIKE :q3)';
            $needle = '%' . $search . '%';
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
        }
        if (in_array($tier, ['basic', 'premium', 'reality_creator'], true)) {
            $where[] = 'c.tier = :tier';
            $params['tier'] = $tier;
        }

        $sql = 'SELECT c.* FROM clients c WHERE ' . implode(' AND ', $where) . ' ' . $scopeSql
             . ' ORDER BY c.full_name ASC LIMIT 500';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        $clients = $stmt->fetchAll();

        render('clients/index', [
            'clients' => $clients,
            'search' => $search,
            'tier' => $tier,
        ]);
    }

    public static function show(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];
        Access::requireCanViewClient($clientId);

        $client = self::find($clientId);
        if (!$client) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $courseRecords = Db::pdo()->prepare(
            'SELECT cr.*, co.title AS course_title FROM course_records cr
             JOIN courses co ON co.id = cr.course_id
             WHERE cr.client_id = :id ORDER BY cr.record_date DESC'
        );
        $courseRecords->execute(['id' => $clientId]);

        $contracts = [];
        $sessionLogs = [];
        if ($client['tier'] === 'reality_creator') {
            $contractStmt = Db::pdo()->prepare(
                'SELECT * FROM contracts WHERE client_id = :id ORDER BY start_date DESC'
            );
            $contractStmt->execute(['id' => $clientId]);
            $contracts = $contractStmt->fetchAll();

            if (Access::canViewSessionLogs()) {
                $logStmt = Db::pdo()->prepare(
                    'SELECT sl.*, u.name AS logged_by_name FROM session_logs sl
                     JOIN users u ON u.id = sl.logged_by
                     WHERE sl.client_id = :id ORDER BY sl.session_date DESC'
                );
                $logStmt->execute(['id' => $clientId]);
                $sessionLogs = $logStmt->fetchAll();
            }
        }

        $courses = Db::pdo()->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();

        Activity::log('client.view', 'client', $clientId);

        render('clients/show', [
            'client' => $client,
            'courseRecords' => $courseRecords->fetchAll(),
            'contracts' => $contracts,
            'sessionLogs' => $sessionLogs,
            'courses' => $courses,
            'canViewSessionLogs' => Access::canViewSessionLogs(),
            'canLogSession' => Access::canLogSession(),
            'canDeleteSessionLog' => Access::canDeleteSessionLog(),
            'canManageContracts' => Access::canManageContracts(),
            'canLogCourseRecord' => Access::canLogCourseRecord($clientId),
            'canEditClient' => Access::canEditClient(),
            'canDeleteClient' => Access::canDeleteClient(),
        ]);
    }

    public static function create(): void
    {
        Auth::requireLogin();
        render('clients/create');
    }

    public static function store(): void
    {
        Auth::requireLogin();

        $name = trim((string) ($_POST['full_name'] ?? ''));
        if ($name === '') {
            flash('error', 'Full name is required.');
            redirect('/clients/new');
        }

        $tier = (string) ($_POST['tier'] ?? 'basic');
        if (!in_array($tier, ['basic', 'premium', 'reality_creator'], true)) {
            $tier = 'basic';
        }
        // Assistants may only create clients they'll be assigned to — the
        // assignment itself is a separate AA/Admin action, so a new client
        // an assistant adds is invisible to them until assigned. That's a
        // deliberate gap to flag with AA rather than silently auto-assign.

        // Normalize once so "Test@x.com" and "test@x.com" are treated as the same
        // client — matches how the CSV importer already compares emails.
        $email = self::nullIfBlank($_POST['email'] ?? '');
        if ($email !== null) {
            $email = mb_strtolower($email);
            $existing = self::findByEmail($email);
            if ($existing) {
                flash('error', $name . ' wasn\'t added — ' . $existing['full_name'] . ' already uses that email. Showing their profile instead.');
                redirect('/clients/' . $existing['id']);
            }
        }

        $stmt = Db::pdo()->prepare(
            'INSERT INTO clients (full_name, email, phone, address, source, tier, notes)
             VALUES (:full_name, :email, :phone, :address, :source, :tier, :notes)'
        );
        try {
            $stmt->execute([
                'full_name' => $name,
                'email' => $email,
                'phone' => self::nullIfBlank($_POST['phone'] ?? ''),
                'address' => self::nullIfBlank($_POST['address'] ?? ''),
                'source' => self::nullIfBlank($_POST['source'] ?? ''),
                'tier' => $tier,
                'notes' => self::nullIfBlank($_POST['notes'] ?? ''),
            ]);
        } catch (PDOException $e) {
            // Belt-and-suspenders against a race between the check above and this
            // insert (two people submitting the same email at once) — only meaningful
            // once the uq_clients_email index from schema.sql is actually in place.
            if ($e->getCode() === '23000' && $email !== null) {
                $existing = self::findByEmail($email);
                if ($existing) {
                    flash('error', $name . ' wasn\'t added — ' . $existing['full_name'] . ' already uses that email. Showing their profile instead.');
                    redirect('/clients/' . $existing['id']);
                }
            }
            throw $e;
        }
        $clientId = (int) Db::pdo()->lastInsertId();
        Activity::log('client.create', 'client', $clientId);

        flash('success', 'Client added.');
        redirect('/clients/' . $clientId);
    }

    /** Edit form — AA and Super Admin only. Admin can add client data but not change what's already there. */
    public static function edit(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];
        Access::requireCanViewClient($clientId);

        if (!Access::canEditClient()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $client = self::find($clientId);
        if (!$client) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        render('clients/edit', ['client' => $client]);
    }

    public static function update(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];

        if (!Access::canEditClient()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $client = self::find($clientId);
        if (!$client) {
            http_response_code(404);
            render('errors/404');
            return;
        }

        $name = trim((string) ($_POST['full_name'] ?? ''));
        if ($name === '') {
            flash('error', 'Full name is required.');
            redirect('/clients/' . $clientId . '/edit');
        }

        $tier = (string) ($_POST['tier'] ?? $client['tier']);
        if (!in_array($tier, ['basic', 'premium', 'reality_creator'], true)) {
            $tier = $client['tier'];
        }

        $email = self::nullIfBlank($_POST['email'] ?? '');
        if ($email !== null) {
            $email = mb_strtolower($email);
            $existing = self::findByEmail($email);
            if ($existing && (int) $existing['id'] !== $clientId) {
                flash('error', 'Not saved — ' . $existing['full_name'] . ' already uses that email.');
                redirect('/clients/' . $clientId . '/edit');
            }
        }

        $stmt = Db::pdo()->prepare(
            'UPDATE clients SET full_name = :full_name, email = :email, phone = :phone,
                address = :address, source = :source, tier = :tier, notes = :notes
             WHERE id = :id'
        );
        try {
            $stmt->execute([
                'full_name' => $name,
                'email' => $email,
                'phone' => self::nullIfBlank($_POST['phone'] ?? ''),
                'address' => self::nullIfBlank($_POST['address'] ?? ''),
                'source' => self::nullIfBlank($_POST['source'] ?? ''),
                'tier' => $tier,
                'notes' => self::nullIfBlank($_POST['notes'] ?? ''),
                'id' => $clientId,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && $email !== null) {
                $existing = self::findByEmail($email);
                if ($existing && (int) $existing['id'] !== $clientId) {
                    flash('error', 'Not saved — ' . $existing['full_name'] . ' already uses that email.');
                    redirect('/clients/' . $clientId . '/edit');
                }
            }
            throw $e;
        }

        Activity::log('client.update', 'client', $clientId);
        flash('success', 'Client updated.');
        redirect('/clients/' . $clientId);
    }

    /** Permanently removes a client and everything tied to it (course records, contracts, session logs, assignments — all cascade). AA and Super Admin only. */
    public static function destroy(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];

        if (!Access::canDeleteClient()) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $client = self::find($clientId);
        if (!$client) {
            flash('error', 'Client not found.');
            redirect('/clients');
        }

        $stmt = Db::pdo()->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $clientId]);

        Activity::log('client.delete', 'client', $clientId, $client['full_name']);
        flash('success', $client['full_name'] . ' and their records were removed.');
        redirect('/clients');
    }

    public static function storeCourseRecord(array $routeParams): void
    {
        Auth::requireLogin();
        $clientId = (int) $routeParams['id'];

        if (!Access::canLogCourseRecord($clientId)) {
            http_response_code(403);
            render('errors/403');
            return;
        }

        $courseId = (int) ($_POST['course_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'attended');
        if (!in_array($type, ['attended', 'purchased'], true) || $courseId <= 0) {
            flash('error', 'Choose a course and a record type.');
            redirect('/clients/' . $clientId);
        }

        $stmt = Db::pdo()->prepare(
            'INSERT INTO course_records (client_id, course_id, type, record_date, amount_paid, completion, source, created_by)
             VALUES (:client_id, :course_id, :type, :record_date, :amount_paid, :completion, \'manual\', :created_by)'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'course_id' => $courseId,
            'type' => $type,
            'record_date' => (string) ($_POST['record_date'] ?? date('Y-m-d')),
            'amount_paid' => self::nullIfBlank($_POST['amount_paid'] ?? ''),
            'completion' => self::nullIfBlank($_POST['completion'] ?? ''),
            'created_by' => Auth::id(),
        ]);
        Activity::log('course_record.create', 'client', $clientId);

        flash('success', 'Course record added.');
        redirect('/clients/' . $clientId);
    }

    private static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function findByEmail(string $email): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT id, full_name FROM clients WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }
}
