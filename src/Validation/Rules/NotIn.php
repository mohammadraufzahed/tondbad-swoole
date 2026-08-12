<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class NotIn implements Rule
{
    public function getName(): string
    {
        return 'not_in';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return !in_array($value, $parameters, true);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The selected :attribute is invalid.';
    }
}
