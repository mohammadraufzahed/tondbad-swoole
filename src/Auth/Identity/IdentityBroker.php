<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

use TondbadSwoole\Contracts\CacheInterface;

class IdentityBroker
{
    private const STATE_TTL = 600;

    public function __construct(
        private readonly string $name,
        private readonly IdentityProvider $provider,
        private readonly CacheInterface $cache,
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

        $this->cache->set($this->stateKey($state), [
            'state' => $state,
            'verifier' => $verifier,
        ], self::STATE_TTL);

        return $this->provider->redirect($callbackUrl, $state, $challenge);
    }

    public function callback(string $code, string $state, string $callbackUrl): IdentityToken
    {
        $stored = $this->cache->get($this->stateKey($state));

        if (!is_array($stored) || !is_string($stored['state'] ?? null) || !hash_equals($stored['state'], $state)) {
            throw new \RuntimeException('Invalid OIDC state.');
        }

        $verifier = (string) ($stored['verifier'] ?? '');

        if ($verifier === '') {
            throw new \RuntimeException('Missing PKCE verifier.');
        }

        $this->cache->delete($this->stateKey($state));

        return $this->provider->callback($code, $callbackUrl, $state, $verifier);
    }

    private function stateKey(string $state): string
    {
        return 'auth.oidc.state.' . $this->name . '.' . $state;
    }

    private function pkceChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
