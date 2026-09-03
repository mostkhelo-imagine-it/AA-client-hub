<?php
declare(strict_types=1);

/**
 * Reads an arbitrary CSV export — the kind that comes out of a CRM,
 * spreadsheet, or course platform, with no fixed column set. Handles the
 * two things that break naive fgetcsv() on real-world exports: a UTF-8
 * BOM (Excel loves adding one) and a delimiter that isn't always a comma
 * (semicolon-delimited exports are common outside the US).
 */
final class Csv
{
    /**
     * @return array{header: array<int,string>, rows: array<int,array<string,string>>, delimiter: string}
     */
    public static function read(string $path, int $maxRows = 0): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException("Could not read $path");
        }
        // Strip a UTF-8 BOM if present.
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $delimiter = self::sniffDelimiter($raw);

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $header = fgetcsv($fh, 0, $delimiter);
        if ($header === false) {
            fclose($fh);
            return ['header' => [], 'rows' => [], 'delimiter' => $delimiter];
        }
        $header = array_map(static fn ($h) => trim((string) $h), $header);
        // Guard against duplicate/blank header names colliding when combined into assoc rows.
        $header = self::dedupeHeader($header);

        $rows = [];
        $count = 0;
        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (count($line) === 1 && $line[0] === null) {
                continue; // blank line
            }
            $line = array_pad($line, count($header), '');
            $line = array_slice($line, 0, count($header));
            $rows[] = array_combine($header, $line);
            $count++;
            if ($maxRows > 0 && $count >= $maxRows) {
                break;
            }
        }
        fclose($fh);

        return ['header' => $header, 'rows' => $rows, 'delimiter' => $delimiter];
    }

    /** Counts data rows without holding the whole file in memory. */
    public static function countRows(string $path): int
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return 0;
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        $delimiter = self::sniffDelimiter($raw);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        fgetcsv($fh, 0, $delimiter); // skip header
        $count = 0;
        while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (count($line) === 1 && $line[0] === null) {
                continue;
            }
            $count++;
        }
        fclose($fh);
        return $count;
    }

    private static function sniffDelimiter(string $raw): string
    {
        $firstLine = strtok($raw, "\r\n") ?: '';
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;
        foreach ($candidates as $d) {
            $count = substr_count($firstLine, $d);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $d;
            }
        }
        return $bestCount > 0 ? $best : ',';
    }

    /** @param array<int,string> $header @return array<int,string> */
    private static function dedupeHeader(array $header): array
    {
        $seen = [];
        foreach ($header as $i => $name) {
            $name = $name === '' ? 'column_' . ($i + 1) : $name;
            $original = $name;
            $n = 1;
            while (isset($seen[$name])) {
                $n++;
                $name = $original . '_' . $n;
            }
            $seen[$name] = true;
            $header[$i] = $name;
        }
        return $header;
    }
}
