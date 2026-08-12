<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Digits implements Rule
{
    public function getName(): string
    {
        return 'digits';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && ctype_digit($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute must be digits.';
    }
}
