<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Contracts\SessionStore;
use TondbadSwoole\Auth\Exceptions\RevokedRefreshTokenException;
use TondbadSwoole\Auth\Session\AuthSession;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Core\Config;

class SessionManager
{
    public function __construct(
        private readonly Config $config,
        private readonly AccessTokenManager $accessTokenManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly SessionStore $sessionStore,
    ) {
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function find(string $id): ?Session
    {
        return $this->sessionStore->get($id);
    }

    public function create(
        string|int $userId,
        array $claims = [],
        ?string $mode = null,
        ?string $deviceFingerprint = null,
        ?string $guardName = null,
    ): AuthSession {
        $mode ??= 'stateful';
        $accessTtl = $this->accessTtl($guardName);
        $refreshTtl = $this->refreshTtl($guardName);

        $sessionId = bin2hex(random_bytes(16));
        $family = $mode === 'stateful' ? bin2hex(random_bytes(16)) : null;
        $antiCsrf = $mode === 'stateful' ? bin2hex(random_bytes(16)) : null;
        $createdAt = time();
        $expiresAt = $createdAt + $accessTtl;

        $session = new Session(
            $sessionId,
            $userId,
            $createdAt,
            $expiresAt,
            $claims,
            $antiCsrf,
            $deviceFingerprint,
            $family,
            $mode,
            'active',
        );

        $refreshToken = null;

        if ($mode === 'stateful') {
            $this->sessionStore->set($session, $accessTtl);
            $refreshToken = $this->refreshTokenRepository->issue($session);
        }

        $accessToken = $this->accessTokenManager->create($session);

        return new AuthSession($session, $accessToken, $refreshToken);
    }

    public function verifyAccessToken(string $value): ?Session
    {
        $payload = $this->accessTokenManager->verify($value);

        if ($payload === null) {
            return null;
        }

        $mode = $payload['mode'] ?? 'stateful';
        $sessionId = (string) ($payload['sid'] ?? '');

        if ($mode === 'stateless') {
            return $this->payloadToSession($payload);
        }

        $session = $this->sessionStore->get($sessionId);

        if ($session === null || $session->status !== 'active' || $session->expiresAt < time()) {
            return null;
        }

        return $session;
    }

    public function refreshSession(string $refreshTokenValue): ?AuthSession
    {
        try {
            $refreshToken = $this->refreshTokenRepository->rotate($refreshTokenValue);
        } catch (RevokedRefreshTokenException $e) {
            if ($e->getFamily() !== null) {
                $this->sessionStore->deleteByFamily($e->getFamily());
            }

            return null;
        }

        $session = $this->sessionStore->get($refreshToken->sessionId);

        if ($session === null || $session->status !== 'active') {
            return null;
        }

        $accessTtl = $this->accessTtl();
        $expiresAt = time() + $accessTtl;

        $session = new Session(
            $session->id,
            $session->userId,
            $session->createdAt,
            $expiresAt,
            $session->claims,
            $session->antiCsrf,
            $session->deviceFingerprint,
            $session->family,
            $session->mode,
            $session->status,
        );

        $this->sessionStore->set($session, $accessTtl);

        $accessToken = $this->accessTokenManager->create($session);

        return new AuthSession($session, $accessToken, $refreshToken);
    }

    public function revokeSession(string $sessionId): void
    {
        $this->sessionStore->delete($sessionId);
        $this->refreshTokenRepository->revokeForSession($sessionId);
    }

    /**
     * @param string|int $userId
     */
    public function revokeAllForUser(string|int $userId): void
    {
        $this->sessionStore->deleteByUser($userId);

        // Refresh-token cleanup per user is not indexed by user_id; a background
        // job or separate scan should purge stale rows. For correctness, the
        // session store is the source of truth for active sessions.
    }

    /**
     * @param array<string, mixed> $value
     */
    public function addClaim(string $sessionId, string $claim, mixed $value): ?Session
    {
        $session = $this->sessionStore->get($sessionId);

        if ($session === null) {
            return null;
        }

        $session = $session->withClaim($claim, $value);
        $ttl = max(1, $session->expiresAt - time());
        $this->sessionStore->set($session, $ttl);

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadToSession(array $payload): Session
    {
        return new Session(
            (string) ($payload['sid'] ?? ''),
            $payload['sub'] ?? '',
            (int) ($payload['iat'] ?? time()),
            (int) ($payload['exp'] ?? time()),
            is_array($payload['claims'] ?? null) ? $payload['claims'] : [],
            $payload['csrf'] ?? null,
            $payload['device'] ?? null,
            $payload['fam'] ?? null,
            (string) ($payload['mode'] ?? 'stateless'),
            'active',
        );
    }

    private function accessTtl(?string $guardName = null): int
    {
        $key = $guardName !== null ? "auth.guards.{$guardName}.access_ttl" : 'auth.access_token_ttl';

        return (int) $this->config->get($key, 900);
    }

    private function refreshTtl(?string $guardName = null): int
    {
        $key = $guardName !== null ? "auth.guards.{$guardName}.refresh_ttl" : 'auth.refresh_token_ttl';

        return (int) $this->config->get($key, 604800);
    }
}
