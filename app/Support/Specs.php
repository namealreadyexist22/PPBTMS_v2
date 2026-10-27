<?php

namespace App\Support;

/**
 * Specifications kept as text, one "Label: value" line per row (e.g. "Memory: 16GB DDR5"), so they
 * copy as they are from the standard item to the PPMP, PR and RFQ and read well printed as text.
 * A line without a label (no colon) is free text. Shown as a two-column table when every line has a label.
 */
class Specs
{
    /** @return array<int, array{0: string, 1: string}> [label, value] per non-empty line ('' label: free text) */
    public static function rows(?string $text): array
    {
        $rows = [];
        foreach (preg_split('/\R/', (string) $text) as $line) {
            if (trim($line) === '') {
                continue;
            }

            // The label is before the first colon and short; a colon between digits ("16:9", "10:30") is no label
            $labelled = preg_match('/^\s*([^:]{1,60}?)\s*:\s*(.*)$/u', $line, $m)
                && ! (preg_match('/\d$/', $m[1]) && preg_match('/^\d/', $m[2]));

            $rows[] = $labelled ? [$m[1], trim($m[2])] : ['', trim($line)];
        }

        return $rows;
    }

    /** Any line is "Label: value": shown as a table (see table()). */
    public static function isTable(?string $text): bool
    {
        return collect(self::rows($text))->contains(fn ($r) => $r[0] !== '');
    }

    /**
     * The rows to show: a line without a label continues the row above it (its requirement runs on
     * to the next line); lines without a label before the first labelled one stay rows of their own.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function table(?string $text): array
    {
        $table = [];
        foreach (self::rows($text) as [$label, $value]) {
            $last = array_key_last($table);
            if ($label === '' && $last !== null && $table[$last][0] !== '') {
                $table[$last][1] = trim($table[$last][1] . "\n" . $value);
            } else {
                $table[] = [$label, $value];
            }
        }

        return $table;
    }
}
