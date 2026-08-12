<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class StringRule implements Rule
{
    public function getName(): string
    {
        return 'string';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be a string.';
    }
}
