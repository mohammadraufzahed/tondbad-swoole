<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class AlphaDash implements Rule
{
    public function getName(): string
    {
        return 'alpha_dash';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute may only contain letters, numbers, dashes and underscores.';
    }
}
