<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Support\Context;

class TokenGuard implements Guard
{
    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly string $storageKey = 'api_token',
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

        $token = $this->getTokenForRequest($request);

        if ($token === null || $token === '') {
            Context::set($cacheKey, null);

            return null;
        }

        $user = $this->provider->retrieveByCredentials([$this->storageKey => $token]);

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

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials + [$this->storageKey => $credentials[$this->storageKey] ?? null]);

        return $user !== null;
    }

    private function getTokenForRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $token = $request->query($this->storageKey);

        return is_string($token) ? $token : null;
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
