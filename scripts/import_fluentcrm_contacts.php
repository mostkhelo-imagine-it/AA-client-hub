<?php
declare(strict_types=1);

/**
 * One-time migration: imports a FluentCRM/FluentCommunity "contacts export"
 * CSV into the clients table.
 *
 * Usage:
 *   php scripts/import_fluentcrm_contacts.php /path/to/contacts_export.csv [--commit]
 *
 * Without --commit, this is a DRY RUN: it prints what it would do and
 * writes review-*.csv files, but touches nothing in the database. Re-run
 * with --commit once the dry run output looks right.
 *
 * What this maps, based on the real export columns (ID, User ID, Title,
 * First Name, Last Name, Email, Address Line 1/2, Postal Code, City,
 * State, Country, IP Address, Phone, Status, Contact Type, Source, Date
 * Of Birth, Last Activity, Created At, Updated At, Lists, Tags):
 *
 *   full_name  = "First Name" + " " + "Last Name" (trimmed)
 *   email      = "Email" (required — rows without one are skipped and
 *                reported, since clients.email has no NOT NULL constraint
 *                but an email is how most of this team finds a client)
 *   phone      = "Phone"
 *   address    = "Address Line 1" (+ ", Address Line 2" if present)
 *   source     = "Source" (web / fluent-community / wp_users / woocommerce)
 *   tier       = 'premium' if the Tags column contains the tag configured
 *                below as PREMIUM_TAG, else 'basic'.
 *
 * What this deliberately does NOT decide:
 *   - Which clients are the 10 Reality Creator (1-on-1) clients. The tag
 *     that looks closest — "برنامج الثقة الحقيقية بالنفس" (a named
 *     program) — is on 13 contacts, not 10, and a program tag isn't the
 *     same claim as "has a signed 1-on-1 contract." Every contact carrying
 *     that tag is written to review-reality-creator-candidates.csv instead
 *     of being auto-promoted to reality_creator. AA/an admin sets that
 *     tier by hand afterward, on the client's profile.
 *   - Duplicate people who show up under more than one email (this
 *     export has a few, e.g. the same name with 2-3 different addresses).
 *     Rows whose name matches an already-imported row are written to
 *     review-possible-duplicates.csv rather than silently merged or
 *     silently duplicated.
 *   - Obviously-test rows (email containing "test") — written to
 *     review-test-rows.csv and skipped either way.
 */

require dirname(__DIR__) . '/src/config.php';
require dirname(__DIR__) . '/src/Db.php';

const PREMIUM_TAG = 'متميز';
const PROGRAM_TAG_HINT_SUBSTRING = 'برنامج'; // Arabic for "program" — catches any course/program tag, not just this one.

$csvPath = $argv[1] ?? null;
$commit = in_array('--commit', $argv, true);

if (!$csvPath || !is_file($csvPath)) {
    fwrite(STDERR, "Usage: php scripts/import_fluentcrm_contacts.php /path/to/export.csv [--commit]\n");
    exit(1);
}

$fh = fopen($csvPath, 'r');
if (!$fh) {
    fwrite(STDERR, "Could not open $csvPath\n");
    exit(1);
}

$header = fgetcsv($fh);
if ($header === false) {
    fwrite(STDERR, "Empty file.\n");
    exit(1);
}
$header = array_map('trim', $header);

$rows = [];
while (($line = fgetcsv($fh)) !== false) {
    if (count($line) !== count($header)) {
        continue; // malformed row — skip rather than misalign columns
    }
    $rows[] = array_combine($header, $line);
}
fclose($fh);

$toImport = [];
$skippedNoEmail = [];
$testRows = [];
$duplicateCandidates = [];
$reviewTier = [];
$seenNames = [];
$seenEmails = [];

foreach ($rows as $row) {
    $firstName = trim($row['First Name'] ?? '');
    $lastName = trim($row['Last Name'] ?? '');
    $fullName = trim($firstName . ' ' . $lastName);
    $email = strtolower(trim($row['Email'] ?? ''));
    $tags = $row['Tags'] ?? '';

    if ($fullName === '') {
        $fullName = $email !== '' ? $email : '(no name)';
    }

    if (str_contains($email, 'test')) {
        $testRows[] = $row;
        continue;
    }

    if ($email === '') {
        $skippedNoEmail[] = $row;
        continue;
    }

    if (isset($seenEmails[$email])) {
        continue; // exact duplicate row for the same email — just skip the repeat
    }
    $seenEmails[$email] = true;

    $nameKey = mb_strtolower($fullName);
    if (isset($seenNames[$nameKey])) {
        $duplicateCandidates[] = $row;
    }
    $seenNames[$nameKey] = true;

    $tier = str_contains($tags, PREMIUM_TAG) ? 'premium' : 'basic';

    if (str_contains($tags, PROGRAM_TAG_HINT_SUBSTRING)) {
        $reviewTier[] = $row;
    }

    $address = trim((string) ($row['Address Line 1'] ?? ''));
    if (!empty($row['Address Line 2'])) {
        $address = trim($address . ', ' . $row['Address Line 2']);
    }

    $toImport[] = [
        'full_name' => $fullName,
        'email' => $email,
        'phone' => trim((string) ($row['Phone'] ?? '')) ?: null,
        'address' => $address ?: null,
        'source' => trim((string) ($row['Source'] ?? '')) ?: null,
        'tier' => $tier,
        'notes' => $tags !== '' ? "Imported tags: $tags" : null,
    ];
}

echo "Parsed " . count($rows) . " rows from $csvPath\n";
echo "  → " . count($toImport) . " will be imported as clients\n";
echo "  → " . count($skippedNoEmail) . " skipped (no email) — see review-no-email.csv\n";
echo "  → " . count($testRows) . " skipped (looked like test data) — see review-test-rows.csv\n";
echo "  → " . count($duplicateCandidates) . " possible duplicate names — see review-possible-duplicates.csv\n";
echo "  → " . count($reviewTier) . " tagged with a program/course — check if they belong in Reality Creator, see review-reality-creator-candidates.csv\n";

$outDir = dirname($csvPath);
write_review_csv($outDir . '/review-no-email.csv', $header, $skippedNoEmail);
write_review_csv($outDir . '/review-test-rows.csv', $header, $testRows);
write_review_csv($outDir . '/review-possible-duplicates.csv', $header, $duplicateCandidates);
write_review_csv($outDir . '/review-reality-creator-candidates.csv', $header, $reviewTier);

if (!$commit) {
    echo "\nDry run only — nothing written to the database. Re-run with --commit to import.\n";
    exit(0);
}

$pdo = Db::pdo();
$stmt = $pdo->prepare(
    'INSERT INTO clients (full_name, email, phone, address, source, tier, notes)
     VALUES (:full_name, :email, :phone, :address, :source, :tier, :notes)
     ON DUPLICATE KEY UPDATE full_name = full_name'
);

$inserted = 0;
$pdo->beginTransaction();
try {
    foreach ($toImport as $client) {
        // clients.email has no unique constraint by default (see schema.sql) —
        // if you add one before running this with --commit, ON DUPLICATE KEY
        // UPDATE above makes re-runs safe instead of erroring out.
        $stmt->execute($client);
        $inserted++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Import failed, nothing was written: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\nImported $inserted clients.\n";
echo "Next: open review-reality-creator-candidates.csv and, for the real 10 contractual clients, set their tier to Reality Creator and add a contract from their profile page.\n";

function write_review_csv(string $path, array $header, array $rows): void
{
    if (!$rows) {
        return;
    }
    $fh = fopen($path, 'w');
    fputcsv($fh, $header);
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
}
