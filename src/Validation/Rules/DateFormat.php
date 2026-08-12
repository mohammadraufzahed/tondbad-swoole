<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use DateTimeImmutable;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class DateFormat implements Rule
{
    public function getName(): string
    {
        return 'date_format';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        if (!is_string($value) || !isset($parameters[0])) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat($parameters[0], $value);

        return $date !== false && $date->format($parameters[0]) === $value;
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute does not match the format :param0.';
    }
}
