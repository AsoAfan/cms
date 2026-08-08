<?php

namespace App\Support;

/**
 * Renders report rows as CSV, for the spreadsheet every business eventually
 * wants a report in.
 *
 * `Money` is written as a plain decimal — no symbol, no thousands separator —
 * so a spreadsheet reads it as a number rather than as text. Formatting is the
 * screen's job, and a "$1,234.56" that will not sum is worse than useless.
 */
final class Csv
{
    /**
     * Excel assumes the host encoding unless a file announces UTF-8 with a
     * byte-order mark, and mangles every accent without one.
     */
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    /**
     * Characters that make a spreadsheet treat a cell as a formula. A supplier
     * called "=cmd|' /c calc'!A0" is a real attack, not a hypothetical one.
     */
    private const string FORMULA_LEADERS = "=+-@\t\r";

    /**
     * @param  list<string>  $headings
     * @param  iterable<array-key, array<array-key, Money|bool|int|string|null>>  $rows
     */
    public static function encode(array $headings, iterable $rows): string
    {
        $lines = [self::line($headings)];

        foreach ($rows as $row) {
            $lines[] = self::line($row);
        }

        // CRLF: the line ending the CSV spec asks for, and the one Excel wants.
        return self::BYTE_ORDER_MARK.implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  array<array-key, Money|bool|int|string|null>  $values
     */
    private static function line(array $values): string
    {
        return implode(',', array_map(self::field(...), $values));
    }

    private static function field(Money|bool|int|string|null $value): string
    {
        $text = match (true) {
            $value === null => '',
            $value instanceof Money => $value->toDecimal(),
            is_bool($value) => $value ? 'yes' : 'no',
            is_int($value) => (string) $value,
            default => self::defuse($value),
        };

        if (preg_match('/[",\r\n]/', $text) !== 1 && trim($text) === $text) {
            return $text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }

    /**
     * Stop a spreadsheet from evaluating text that a user typed.
     *
     * Numbers pass through untouched — a negative amount has to stay negative,
     * and it cannot be a formula.
     */
    private static function defuse(string $value): string
    {
        if ($value === '' || is_numeric($value)) {
            return $value;
        }

        return str_contains(self::FORMULA_LEADERS, $value[0]) ? "'".$value : $value;
    }
}
