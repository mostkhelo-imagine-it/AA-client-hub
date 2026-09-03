<?php
declare(strict_types=1);

final class ImportController
{
    private const TARGET_FIELDS = ['full_name', 'first_name', 'last_name', 'email', 'phone', 'address', 'source', 'notes', 'tags'];
    private const PREVIEW_ROWS = 8;

    public static function showForm(): void
    {
        self::guard();
        render('imports/form');
    }

    /** Step 1: accept the file, parse a preview, guess the mapping, hand off to the mapping screen. */
    public static function preview(): void
    {
        self::guard();

        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Choose a CSV or Excel (.xlsx) file to upload.');
            redirect('/clients/import');
        }

        $extension = Tabular::extensionOf($file['name']);
        if (!Tabular::isSupported($file['name'])) {
            flash('error', 'Only .csv and .xlsx files are supported.');
            redirect('/clients/import');
        }

        $storageDir = self::storageDir();
        $token = bin2hex(random_bytes(16));
        $storedPath = $storageDir . '/' . $token . '.' . $extension;

        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            flash('error', 'Could not read that upload — try again.');
            redirect('/clients/import');
        }

        try {
            $parsed = Tabular::read($storedPath, $extension, self::PREVIEW_ROWS);
        } catch (Throwable $e) {
            @unlink($storedPath);
            flash('error', $e->getMessage() ?: "That doesn't look like a valid file.");
            redirect('/clients/import');
        }

        if (!$parsed['header']) {
            @unlink($storedPath);
            flash('error', 'The file has no header row to map columns from.');
            redirect('/clients/import');
        }

        $totalRows = Tabular::countRows($storedPath, $extension);
        $guess = ImportMapper::guess($parsed['header']);

        Activity::log('import.upload', null, null, "$token.$extension ($totalRows rows)");

        render('imports/map', [
            'token' => $token,
            'extension' => $extension,
            'header' => $parsed['header'],
            'previewRows' => $parsed['rows'],
            'totalRows' => $totalRows,
            'guess' => $guess,
        ]);
    }

    /** Step 2: apply the confirmed mapping and write clients rows. */
    public static function commit(): void
    {
        self::guard();

        $token = (string) ($_POST['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            flash('error', 'That import session expired — upload the file again.');
            redirect('/clients/import');
        }
        $extension = (string) ($_POST['ext'] ?? '');
        if (!in_array($extension, Tabular::ACCEPTED_EXTENSIONS, true)) {
            flash('error', 'That import session expired — upload the file again.');
            redirect('/clients/import');
        }
        $path = self::storageDir() . '/' . $token . '.' . $extension;
        if (!is_file($path)) {
            flash('error', 'That import session expired — upload the file again.');
            redirect('/clients/import');
        }

        $mapping = [];
        foreach (self::TARGET_FIELDS as $field) {
            $col = (string) ($_POST['map'][$field] ?? '');
            $mapping[$field] = $col !== '' ? $col : null;
        }

        $defaultTier = (string) ($_POST['default_tier'] ?? 'basic');
        if (!in_array($defaultTier, ['basic', 'premium', 'reality_creator'], true)) {
            $defaultTier = 'basic';
        }
        $premiumKeyword = mb_strtolower(trim((string) ($_POST['premium_keyword'] ?? '')));
        $realityKeyword = mb_strtolower(trim((string) ($_POST['reality_creator_keyword'] ?? '')));
        $autoPromote = !empty($_POST['auto_promote_reality_creator']);
        // Unchecked checkboxes aren't sent at all — presence in $_POST is the signal, not its value.
        $skipDuplicates = isset($_POST['skip_duplicates']);

        $parsed = Tabular::read($path, $extension);

        $existingEmails = self::existingEmails();

        $imported = 0;
        $skippedBlank = 0;
        $skippedDuplicate = 0;
        $flaggedForReview = [];
        $seenInFile = [];

        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO clients (full_name, email, phone, address, source, tier, notes)
             VALUES (:full_name, :email, :phone, :address, :source, :tier, :notes)'
        );

        $pdo->beginTransaction();
        try {
            foreach ($parsed['rows'] as $row) {
                $fullName = self::resolveName($row, $mapping);
                $email = $mapping['email'] ? mb_strtolower(trim((string) ($row[$mapping['email']] ?? ''))) : '';

                if ($fullName === '' && $email === '') {
                    $skippedBlank++;
                    continue;
                }

                if ($email !== '') {
                    if ($skipDuplicates && (isset($existingEmails[$email]) || isset($seenInFile[$email]))) {
                        $skippedDuplicate++;
                        continue;
                    }
                    $seenInFile[$email] = true;
                }

                $tagsValue = $mapping['tags'] ? (string) ($row[$mapping['tags']] ?? '') : '';
                $tagsLower = mb_strtolower($tagsValue);

                $tier = $defaultTier;
                if ($premiumKeyword !== '' && str_contains($tagsLower, $premiumKeyword)) {
                    $tier = 'premium';
                }
                if ($realityKeyword !== '' && str_contains($tagsLower, $realityKeyword)) {
                    if ($autoPromote) {
                        $tier = 'reality_creator';
                    } else {
                        $flaggedForReview[] = ['name' => $fullName, 'email' => $email];
                    }
                }

                $notes = $mapping['notes'] ? trim((string) ($row[$mapping['notes']] ?? '')) : '';
                if ($tagsValue !== '') {
                    $notes = trim($notes . ($notes !== '' ? "\n" : '') . "Imported tags: $tagsValue");
                }

                $stmt->execute([
                    'full_name' => $fullName !== '' ? $fullName : $email,
                    'email' => $email !== '' ? $email : null,
                    'phone' => self::col($row, $mapping, 'phone'),
                    'address' => self::col($row, $mapping, 'address'),
                    'source' => self::col($row, $mapping, 'source'),
                    'tier' => $tier,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $imported++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            @unlink($path);
            flash('error', 'Import failed partway through, nothing was saved: ' . $e->getMessage());
            redirect('/clients/import');
        }

        @unlink($path); // don't leave the uploaded file (client PII) sitting on disk once it's imported
        Activity::log('import.commit', null, null, "$imported imported, $skippedDuplicate duplicates, $skippedBlank blank");

        render('imports/result', [
            'imported' => $imported,
            'skippedDuplicate' => $skippedDuplicate,
            'skippedBlank' => $skippedBlank,
            'flaggedForReview' => $flaggedForReview,
        ]);
    }

    private static function resolveName(array $row, array $mapping): string
    {
        if ($mapping['full_name']) {
            $name = trim((string) ($row[$mapping['full_name']] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
        $first = $mapping['first_name'] ? trim((string) ($row[$mapping['first_name']] ?? '')) : '';
        $last = $mapping['last_name'] ? trim((string) ($row[$mapping['last_name']] ?? '')) : '';
        return trim($first . ' ' . $last);
    }

    private static function col(array $row, array $mapping, string $field): ?string
    {
        if (!$mapping[$field]) {
            return null;
        }
        $value = trim((string) ($row[$mapping[$field]] ?? ''));
        return $value !== '' ? $value : null;
    }

    /** @return array<string,bool> lowercased emails already in clients, for O(1) dedupe lookups */
    private static function existingEmails(): array
    {
        $rows = Db::pdo()->query('SELECT email FROM clients WHERE email IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        $set = [];
        foreach ($rows as $email) {
            $set[mb_strtolower((string) $email)] = true;
        }
        return $set;
    }

    private static function storageDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir;
    }

    private static function guard(): void
    {
        Auth::requireLogin();
        if (!Access::canImportClients()) {
            http_response_code(403);
            render('errors/403');
            exit;
        }
    }
}
