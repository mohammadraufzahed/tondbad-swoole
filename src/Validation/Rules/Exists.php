<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Exists implements Rule
{
    public function getName(): string
    {
        return 'exists';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if ($databaseManager === null) {
            return true;
        }

        if ($value === null) {
            return false;
        }

        $table = $parameters[0] ?? '';
        $column = $parameters[1] ?? $attribute;

        if ($table === '') {
            return true;
        }

        return $databaseManager->connection()->table($table)->where($column, '=', (string) $value)->count() > 0;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The selected :attribute is invalid.';
    }
}
