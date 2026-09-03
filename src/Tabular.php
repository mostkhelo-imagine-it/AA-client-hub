<?php
declare(strict_types=1);

/**
 * Picks Csv or Xlsx based on the file's extension and hands back the same
 * {header, rows} shape either way, so ImportController and the mapping
 * screen don't care which format was actually uploaded.
 */
final class Tabular
{
    public const ACCEPTED_EXTENSIONS = ['csv', 'xlsx'];

    public static function extensionOf(string $filename): string
    {
        return mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function isSupported(string $filename): bool
    {
        return in_array(self::extensionOf($filename), self::ACCEPTED_EXTENSIONS, true);
    }

    /** @return array{header: array<int,string>, rows: array<int,array<string,string>>} */
    public static function read(string $path, string $extension, int $maxRows = 0): array
    {
        return $extension === 'xlsx'
            ? Xlsx::read($path, $maxRows)
            : Csv::read($path, $maxRows);
    }

    public static function countRows(string $path, string $extension): int
    {
        return $extension === 'xlsx'
            ? Xlsx::countRows($path)
            : Csv::countRows($path);
    }
}
