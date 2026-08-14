<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Strategies;

use TondbadSwoole\Auth\Contracts\Authenticatable;

interface AuthStrategy
{
    public function name(): string;

    /**
     * @param array<string, mixed> $credentials
     */
    public function authenticate(array $credentials): ?Authenticatable;

    /**
     * @param array<string, mixed> $data
     */
    public function register(array $data): ?Authenticatable;
}
