<?php

declare(strict_types=1);

namespace TondbadSwoole\Validation\Contracts;

use TondbadSwoole\Database\DatabaseManager;

interface Rule
{
    public function getName(): string;

    /**
     * @param list<string> $parameters
     * @param array<string, mixed> $data
     */
    public function passes(mixed $value, string $attribute, array $parameters, array $data, ?DatabaseManager $databaseManager): bool;

    /**
     * @param list<string> $parameters
     */
    public function message(string $attribute, array $parameters): string;
}
