<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Support\Context;

class BasicAuthGuard implements Guard
{
    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly string $usernameKey = 'email',
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

        $credentials = $this->getBasicCredentials($request);

        if ($credentials === null) {
            Context::set($cacheKey, null);

            return null;
        }

        $user = $this->provider->retrieveByCredentials([$this->usernameKey => $credentials['username']]);

        if ($user === null || !$this->provider->validateCredentials($user, ['password' => $credentials['password']])) {
            Context::set($cacheKey, null);

            return null;
        }

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
        $user = $this->provider->retrieveByCredentials([$this->usernameKey => $credentials[$this->usernameKey] ?? '']);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * @return array{username: string, password: string}|null
     */
    private function getBasicCredentials(Request $request): ?array
    {
        $header = $request->header('Authorization');

        if (!is_string($header) || !str_starts_with($header, 'Basic ')) {
            return null;
        }

        $decoded = base64_decode(substr($header, 6), true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        [$username, $password] = explode(':', $decoded, 2);

        if ($username === '' || $password === '') {
            return null;
        }

        return ['username' => $username, 'password' => $password];
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
