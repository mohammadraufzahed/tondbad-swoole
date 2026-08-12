<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Numeric implements Rule
{
    public function getName(): string
    {
        return 'numeric';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_numeric($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be numeric.';
    }
}
