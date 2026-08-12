<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Different implements Rule
{
    public function getName(): string
    {
        return 'different';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        $key = $parameters[0] ?? '';

        return !isset($data[$key]) || $data[$key] !== $value;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute and :param0 must be different.';
    }
}
