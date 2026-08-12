<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class DigitsBetween implements Rule
{
    public function getName(): string
    {
        return 'digits_between';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if (!is_string($value) || !isset($parameters[0], $parameters[1])) {
            return false;
        }

        if (!ctype_digit($value)) {
            return false;
        }

        $min = (int) $parameters[0];
        $max = (int) $parameters[1];
        $length = strlen($value);

        return $length >= $min && $length <= $max;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must have between :param0 and :param1 digits.';
    }
}
