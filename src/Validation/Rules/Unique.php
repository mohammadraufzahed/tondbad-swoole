<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Unique implements Rule
{
    public function getName(): string
    {
        return 'unique';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if ($databaseManager === null) {
            return true;
        }

        if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
            return false;
        }

        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;

        if ($table === '') {
            return true;
        }

        return $databaseManager->connection()->table($table)->where($column, '=', (string) $value)->count() === 0;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute has already been taken.';
    }
}
