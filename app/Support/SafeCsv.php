<?php

namespace App\Support;

/**
 * CSV rows that are safe to open in Excel / Google Sheets / LibreOffice.
 *
 * Guest-supplied text (names, emails, special requests, gateway
 * references) ends up in staff exports. A cell starting with = + - @ (or a
 * tab / carriage return) is treated by spreadsheets as a FORMULA — e.g. a
 * guest named "=HYPERLINK(...)" could run when staff open the file
 * ("CSV/formula injection", OWASP). Such cells are prefixed with a single
 * quote, which spreadsheets display as plain text without the quote.
 *
 * Genuine numbers are left alone (amounts, nights, "-150.00", and phone
 * numbers in E.164 such as "+9779812345678", which are plain numerals),
 * so normal data is not altered.
 */
final class SafeCsv
{
    private const DANGEROUS_FIRST_CHARACTERS = ['=', '+', '-', '@', "\t", "\r", "\n"];

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        // Leading whitespace doesn't stop a spreadsheet evaluating what follows.
        $trimmed = ltrim($value, " \0\x0B");

        if ($trimmed !== '' && in_array($trimmed[0], self::DANGEROUS_FIRST_CHARACTERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }

    /**
     * fputcsv() with every cell neutralised.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public static function put($handle, array $row): void
    {
        fputcsv($handle, self::row($row));
    }
}
