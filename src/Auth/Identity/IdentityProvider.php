<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

interface IdentityProvider
{
    public function name(): string;

    public function redirect(string $callbackUrl, string $state, string $codeChallenge): string;

    public function callback(string $code, string $callbackUrl, string $state, string $codeVerifier): IdentityToken;
}
