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
            $where[] = '(c.full_name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q)';
            $params['q'] = '%' . $search . '%';
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

        $stmt = Db::pdo()->prepare(
            'INSERT INTO clients (full_name, email, phone, address, source, tier, notes)
             VALUES (:full_name, :email, :phone, :address, :source, :tier, :notes)'
        );
        $stmt->execute([
            'full_name' => $name,
            'email' => self::nullIfBlank($_POST['email'] ?? ''),
            'phone' => self::nullIfBlank($_POST['phone'] ?? ''),
            'address' => self::nullIfBlank($_POST['address'] ?? ''),
            'source' => self::nullIfBlank($_POST['source'] ?? ''),
            'tier' => $tier,
            'notes' => self::nullIfBlank($_POST['notes'] ?? ''),
        ]);
        $clientId = (int) Db::pdo()->lastInsertId();
        Activity::log('client.create', 'client', $clientId);

        flash('success', 'Client added.');
        redirect('/clients/' . $clientId);
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

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }
}
