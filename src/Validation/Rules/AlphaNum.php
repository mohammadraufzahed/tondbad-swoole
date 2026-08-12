<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Rules;

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\Contracts\Rule;

class AlphaNum implements Rule
{
    public function getName(): string
    {
        return 'alpha_num';
    }

    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool
    {
        return is_string($value) && ctype_alnum($value);
    }

    public function message(string $attribute, array $parameters): string
    {
        return 'The :attribute may only contain letters and numbers.';
    }
}
