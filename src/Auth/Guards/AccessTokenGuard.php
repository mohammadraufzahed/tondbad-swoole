<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Guards;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\StatefulGuard;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Auth\Session\AuthSession;
use TondbadSwoole\Auth\SessionManager;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Http\Request;

class AccessTokenGuard implements StatefulGuard
{
    private string $mode;

    public function __construct(
        private readonly string $name,
        private readonly UserProvider $provider,
        private readonly SessionManager $sessionManager,
        private readonly ContextInterface $context,
        private readonly Config $config,
    ) {
        $guardConfig = $config->get("auth.guards.{$name}", []);
        $this->mode = (string) ($guardConfig['mode'] ?? 'stateful');
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

        $cacheKey = $this->userCacheKey();

        if ($this->context->has($cacheKey)) {
            return $this->context->get($cacheKey);
        }

        $token = $this->getTokenForRequest($request);

        if ($token === null || $token === '') {
            $this->context->set($cacheKey, null);

            return null;
        }

        $session = $this->sessionManager->verifyAccessToken($token);

        if ($session === null) {
            $this->context->set($cacheKey, null);

            return null;
        }

        $user = $this->provider->retrieveById($session->userId);

        if ($user !== null) {
            $this->context->set($this->sessionCacheKey(), $session);
        }

        $this->context->set($cacheKey, $user);

        return $user;
    }

    public function id(): string|int|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function setUser(Authenticatable $user): self
    {
        $this->context->set($this->userCacheKey(), $user);

        return $this;
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function login(Authenticatable $user, array $claims = []): AuthSession
    {
        $authSession = $this->sessionManager->create(
            $user->getAuthIdentifier(),
            $claims,
            $this->mode,
        );

        $this->context->set($this->sessionCacheKey(), $authSession->session);
        $this->context->set($this->userCacheKey(), $user);

        return $authSession;
    }

    public function logout(): void
    {
        $session = $this->context->get($this->sessionCacheKey());

        if ($session instanceof \TondbadSwoole\Auth\Session\Session) {
            $this->sessionManager->revokeSession($session->id);
        }

        $this->context->delete($this->sessionCacheKey());
        $this->context->delete($this->userCacheKey());
    }

    private function getTokenForRequest(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }

    private function request(): ?Request
    {
        return $this->context->get('request');
    }

    private function userCacheKey(): string
    {
        return 'auth.guard.' . $this->name . '.user';
    }

    private function sessionCacheKey(): string
    {
        return 'auth.guard.' . $this->name . '.session';
    }
}
