<?php

namespace App\Support;

class SpreadsheetSafeText
{
    public static function csv(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r\n]/u', $text) === 1 ? "'".$text : $text;
    }

    public static function row(array $values): array
    {
        return array_map(self::csv(...), $values);
    }
}
