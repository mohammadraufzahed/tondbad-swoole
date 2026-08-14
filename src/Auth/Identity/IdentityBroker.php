<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

use TondbadSwoole\Contracts\ContextInterface;

class IdentityBroker
{
    private const STATE_KEY = 'auth.oidc.state.';
    private const PKCE_KEY = 'auth.oidc.pkce.';

    public function __construct(
        private readonly string $name,
        private readonly IdentityProvider $provider,
        private readonly ContextInterface $context,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function redirect(string $callbackUrl): string
    {
        $state = bin2hex(random_bytes(16));
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = $this->pkceChallenge($verifier);

        $this->context->set(self::STATE_KEY . $this->name, $state);
        $this->context->set(self::PKCE_KEY . $this->name, $verifier);

        return $this->provider->redirect($callbackUrl, $state, $challenge);
    }

    public function callback(string $code, string $state, string $callbackUrl): IdentityToken
    {
        $expectedState = $this->context->get(self::STATE_KEY . $this->name);

        if (!is_string($expectedState) || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('Invalid OIDC state.');
        }

        $verifier = $this->context->get(self::PKCE_KEY . $this->name);

        if (!is_string($verifier) || $verifier === '') {
            throw new \RuntimeException('Missing PKCE verifier.');
        }

        $this->context->delete(self::STATE_KEY . $this->name);
        $this->context->delete(self::PKCE_KEY . $this->name);

        return $this->provider->callback($code, $callbackUrl, $state, $verifier);
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
