<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Date implements Rule
{
    public function getName(): string
    {
        return 'date';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute is not a valid date.';
    }
}
