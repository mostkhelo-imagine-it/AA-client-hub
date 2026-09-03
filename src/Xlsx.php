<?php
declare(strict_types=1);

/**
 * Reads the first sheet of a modern .xlsx workbook — no PhpSpreadsheet,
 * no Composer. An .xlsx is a zip of XML files, and PHP's bundled
 * ZipArchive + SimpleXML are enough to pull values out of it, which
 * keeps this deploying the same way as the rest of the app: a zip
 * upload or a git pull, nothing to `composer install`.
 *
 * Deliberately out of scope: the legacy binary .xls format (a different,
 * much more involved format to parse without a library), formulas
 * (the cached last-computed value is read instead, same as most
 * lightweight readers do), and real date formatting (a date cell comes
 * back as its raw Excel serial number, not a calendar date) — none of
 * those matter for the columns this importer actually maps (name,
 * email, phone, tags, etc.), so it wasn't worth the added complexity.
 */
final class Xlsx
{
    /**
     * @return array{header: array<int,string>, rows: array<int,array<string,string>>}
     */
    public static function read(string $path, int $maxRows = 0): array
    {
        $zip = self::open($path);
        $sheetPath = self::firstSheetPath($zip);
        $sharedStrings = self::readSharedStrings($zip);

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('Could not read the worksheet inside that file.');
        }

        $xml = self::parseXml($sheetXml);
        $rowsRaw = self::extractRows($xml, $sharedStrings);

        if (!$rowsRaw) {
            return ['header' => [], 'rows' => []];
        }

        $header = array_map(static fn ($h) => trim((string) $h), $rowsRaw[0]);
        $header = self::dedupeHeader($header);
        $colCount = count($header);

        $rows = [];
        $count = 0;
        for ($i = 1; $i < count($rowsRaw); $i++) {
            $line = $rowsRaw[$i];
            $line = array_pad($line, $colCount, '');
            $line = array_slice($line, 0, $colCount);
            if (self::isBlankRow($line)) {
                continue;
            }
            $rows[] = array_combine($header, $line);
            $count++;
            if ($maxRows > 0 && $count >= $maxRows) {
                break;
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }

    public static function countRows(string $path): int
    {
        $zip = self::open($path);
        $sheetPath = self::firstSheetPath($zip);
        $sharedStrings = self::readSharedStrings($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheetXml === false) {
            return 0;
        }
        $xml = self::parseXml($sheetXml);
        $rowsRaw = self::extractRows($xml, $sharedStrings);
        if (!$rowsRaw) {
            return 0;
        }
        $count = 0;
        for ($i = 1; $i < count($rowsRaw); $i++) {
            if (!self::isBlankRow($rowsRaw[$i])) {
                $count++;
            }
        }
        return $count;
    }

    private static function open(string $path): ZipArchive
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The PHP zip extension is required to read .xlsx files but is not enabled on this server.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('That file is not a valid .xlsx workbook.');
        }
        return $zip;
    }

    private static function parseXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);
        if ($doc === false) {
            throw new RuntimeException('Could not parse the workbook — the file may be corrupted.');
        }
        return $doc;
    }

    /** Resolves the first sheet's XML path via workbook.xml + its rels, falling back to the common default. */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = self::parseXml($workbookXml);
        $rels = self::parseXml($relsXml);

        $firstSheet = $workbook->sheets->sheet[0] ?? null;
        if ($firstSheet === null) {
            return 'xl/worksheets/sheet1.xml';
        }
        $rId = (string) $firstSheet->attributes('r', true)->id;

        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $target = ltrim((string) $rel['Target'], '/');
                return str_starts_with($target, 'worksheets/') ? 'xl/' . $target : $target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /** @return array<int,array<int,string>> shared string index => text */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xmlRaw = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlRaw === false) {
            return [];
        }
        $xml = self::parseXml($xmlRaw);
        $strings = [];
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                // Rich text: concatenate every run's text.
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $strings[] = $text;
            }
        }
        return $strings;
    }

    /** @return array<int,array<int,string>> zero-indexed rows of zero-indexed columns */
    private static function extractRows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowIndex = (int) $row['r'] - 1;
            if ($rowIndex < 0) {
                $rowIndex = count($rows);
            }
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $colIndex = $ref !== '' ? self::columnIndexFromRef($ref) : count($cells);
                $type = (string) $cell['t'];

                if ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } elseif ($type === 's') {
                    $idx = (int) $cell->v;
                    $value = $sharedStrings[$idx] ?? '';
                } else {
                    // Numeric, boolean, or a formula's cached result — all land in <v>.
                    $value = (string) $cell->v;
                }
                $cells[$colIndex] = $value;
            }
            if (!$cells) {
                continue;
            }
            $maxCol = max(array_keys($cells));
            $line = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $line[$c] = $cells[$c] ?? '';
            }
            $rows[$rowIndex] = $line;
        }
        ksort($rows);
        return array_values($rows);
    }

    private static function columnIndexFromRef(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letters = $m[1] ?? 'A';
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    private static function isBlankRow(array $line): bool
    {
        foreach ($line as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
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
