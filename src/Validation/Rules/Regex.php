<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Regex implements Rule
{
    public function getName(): string
    {
        return 'regex';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }

        return @preg_match($parameters[0], $value) === 1;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute format is invalid.';
    }
}
