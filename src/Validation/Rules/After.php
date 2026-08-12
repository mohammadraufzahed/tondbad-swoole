<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class After implements Rule
{
    public function getName(): string
    {
        return 'after';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }

        $other = $parameters[0];

        if (array_key_exists($other, $data)) {
            $other = $data[$other];
        }

        return strtotime($value) > strtotime($other);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be a date after :param0.';
    }
}
