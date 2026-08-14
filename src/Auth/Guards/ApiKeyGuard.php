<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Http\Request;

class ApiKeyGuard implements Guard
{
    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly ContextInterface $context,
        private readonly string $storageKey = 'api_key',
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

        $key = $this->getKeyForRequest($request);

        if ($key === null || $key === '') {
            $this->context->set($cacheKey, null);

            return null;
        }

        $user = $this->provider->retrieveByCredentials([$this->storageKey => $key]);

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

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials + [$this->storageKey => $credentials[$this->storageKey] ?? null]);

        return $user !== null;
    }

    private function getKeyForRequest(Request $request): ?string
    {
        $header = $request->header('X-Api-Key');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $key = $request->query($this->storageKey);

        return is_string($key) ? $key : null;
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
