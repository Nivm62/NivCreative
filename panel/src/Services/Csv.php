<?php
declare(strict_types=1);

namespace Nivc\Services;

/** CSV with UTF-8 BOM (Excel-friendly for Hebrew) and spreadsheet-formula injection protection. */
final class Csv
{
    /** @param string[] $header @param iterable<array<int,mixed>> $rows */
    public static function build(array $header, iterable $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map([self::class, 'cell'], $header), ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($fh, array_map([self::class, 'cell'], $r), ',', '"', '\\');
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    private static function cell(mixed $v): string
    {
        $s = (string) $v;
        // Prevent CSV/Excel formula injection.
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) {
            return "'" . $s;
        }
        return $s;
    }
}
