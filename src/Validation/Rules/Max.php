<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class Max implements Rule
{
    public function getName(): string
    {
        return 'max';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if (!isset($parameters[0])) {
            return true;
        }

        $limit = (int) $parameters[0];

        if (is_array($value)) {
            return count($value) <= $limit;
        }

        if (is_numeric($value) && !is_bool($value)) {
            return (float) $value <= $limit;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $limit;
        }

        return true;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute may not be greater than :param0.';
    }
}
