<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class ArrayRule implements Rule
{
    public function getName(): string
    {
        return 'array';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_array($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be an array.';
    }
}
