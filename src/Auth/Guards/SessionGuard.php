<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Support\Context;

class SessionGuard implements Guard
{
    private const CACHE_PREFIX = 'auth_session_';

    private ?string $sessionId = null;

    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly CacheInterface $cache,
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

        $cacheKey = $this->cacheKey($request);

        if (Context::has($cacheKey)) {
            return Context::get($cacheKey);
        }

        $sessionId = $request->cookie($this->sessionKey);

        if (!is_string($sessionId) || $sessionId === '') {
            Context::set($cacheKey, null);

            return null;
        }

        $userId = $this->cache->get(self::CACHE_PREFIX . $sessionId);

        if ($userId === null) {
            Context::set($cacheKey, null);

            return null;
        }

        $this->sessionId = $sessionId;
        $user = $this->provider->retrieveById($userId);

        Context::set($cacheKey, $user);

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
            Context::set($this->cacheKey($request), $user);
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
            Context::set($this->cacheKey($request), $user);
        }

        Context::set('session.id', $sessionId);

        return $sessionId;
    }

    public function logout(): void
    {
        $sessionId = $this->sessionId ?? Context::get('session.id');

        if (is_string($sessionId) && $sessionId !== '') {
            $this->cache->delete(self::CACHE_PREFIX . $sessionId);
        }

        $request = $this->request();

        if ($request !== null) {
            Context::delete($this->cacheKey($request));
        }

        Context::delete('session.id');
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    private function request(): ?Request
    {
        return Context::get('request');
    }

    private function cacheKey(Request $request): string
    {
        return 'auth.guard.' . $this->name . '.user.' . spl_object_id($request);
    }
}
