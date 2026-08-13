<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Http\Request;

class SessionGuard implements Guard
{
    private const CACHE_PREFIX = 'auth_session_';

    private ?string $sessionId = null;

    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly CacheInterface $cache,
        private readonly ContextInterface $context,
        private readonly string $sessionKey = 'session_id',
        private readonly int $lifetime = 7200,
    ) {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Authenticatable
    {
        $request = $this->request();

        if ($request === null) {
            return null;
        }

        $cacheKey = $this->cacheKey();

        if ($this->context->has($cacheKey)) {
            return $this->context->get($cacheKey);
        }

        $sessionId = $request->cookie($this->sessionKey);

        if (!is_string($sessionId) || $sessionId === '') {
            $this->context->set($cacheKey, null);

            return null;
        }

        $userId = $this->cache->get(self::CACHE_PREFIX . $sessionId);

        if ($userId === null) {
            $this->context->set($cacheKey, null);

            return null;
        }

        $this->sessionId = $sessionId;
        $user = $this->provider->retrieveById($userId);

        $this->context->set($cacheKey, $user);

        return $user;
    }

    public function id(): string|int|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function setUser(Authenticatable $user): self
    {
        $request = $this->request();

        if ($request !== null) {
            $this->context->set($this->cacheKey(), $user);
        }

        return $this;
    }

    public function login(Authenticatable $user): string
    {
        $sessionId = bin2hex(random_bytes(16));

        $this->cache->set(self::CACHE_PREFIX . $sessionId, $user->getAuthIdentifier(), $this->lifetime);

        $this->sessionId = $sessionId;

        $request = $this->request();

        if ($request !== null) {
            $this->context->set($this->cacheKey(), $user);
        }

        $this->context->set('session.id', $sessionId);

        return $sessionId;
    }

    public function logout(): void
    {
        $sessionId = $this->sessionId ?? $this->context->get('session.id');

        if (is_string($sessionId) && $sessionId !== '') {
            $this->cache->delete(self::CACHE_PREFIX . $sessionId);
        }

        $request = $this->request();

        if ($request !== null) {
            $this->context->delete($this->cacheKey());
        }

        $this->context->delete('session.id');
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    private function request(): ?Request
    {
        return $this->context->get('request');
    }

    private function cacheKey(): string
    {
        return 'auth.guard.' . $this->name . '.user';
    }
}
