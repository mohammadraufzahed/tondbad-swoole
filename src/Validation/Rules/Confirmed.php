<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Confirmed implements Rule
{
    public function getName(): string
    {
        return 'confirmed';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        $key = "{$attribute}_confirmation";

        return isset($data[$key]) && $data[$key] === $value;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute confirmation does not match.';
    }
}
