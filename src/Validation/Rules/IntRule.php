<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class IntRule implements Rule
{
    public function getName(): string
    {
        return 'int';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be an integer.';
    }
}
