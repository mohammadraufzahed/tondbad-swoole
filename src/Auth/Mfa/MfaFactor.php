<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Mfa;

use TondbadSwoole\Auth\Contracts\Authenticatable;

interface MfaFactor
{
    /**
     * @return array<string, mixed>
     */
    public function challenge(Authenticatable $user): array;

    public function verify(Authenticatable $user, string $input): bool;

    public function type(): string;
}
