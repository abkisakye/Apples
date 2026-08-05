<?php

namespace App\Support;

class MoneyInput
{
    public static function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/', $value)) {
            return $value;
        }

        return str_replace(',', '', $value);
    }
}
