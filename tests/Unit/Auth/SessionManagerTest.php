<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AccessTokenManager;
use TondbadSwoole\Auth\RefreshTokenRepository;
use TondbadSwoole\Auth\SessionManager;
use TondbadSwoole\Auth\SessionStores\DatabaseSessionStore;
use TondbadSwoole\Database\DatabaseManager;

beforeEach(function () {
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.database', ':memory:');
    $this->config->set('app.key', 'test-secret-key-at-least-32-characters');
    $this->config->set('auth.access_token_ttl', 60);
    $this->config->set('auth.refresh_token_ttl', 120);

    $this->manager = new DatabaseManager($this->config);
    $pdo = $this->manager->connection()->getPdo();
    $pdo->exec('CREATE TABLE sessions (
        id VARCHAR(36) PRIMARY KEY,
        user_id VARCHAR(255),
        claims TEXT,
        anti_csrf VARCHAR(64),
        device VARCHAR(255),
        family VARCHAR(36),
        status VARCHAR(20) DEFAULT "active",
        expires_at INTEGER,
        created_at INTEGER
    )');
    $pdo->exec('CREATE TABLE refresh_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id VARCHAR(36),
        family VARCHAR(36),
        parent INTEGER,
        token_hash VARCHAR(64) UNIQUE,
        used_at INTEGER,
        revoked INTEGER DEFAULT 0,
        expires_at INTEGER,
        created_at INTEGER
    )');

    $store = new DatabaseSessionStore($this->manager);
    $accessTokenManager = new AccessTokenManager($this->config);
    $refreshRepo = new RefreshTokenRepository($this->manager, $this->config);

    $this->sessionManager = new SessionManager(
        $this->config,
        $accessTokenManager,
        $refreshRepo,
        $store,
    );
});

it('creates a session with access and refresh tokens', function () {
    $authSession = $this->sessionManager->create(1, ['roles' => ['user']]);

    expect($authSession->session->userId)->toBe(1);
    expect($authSession->session->claims)->toBe(['roles' => ['user']]);
    expect($authSession->accessToken->value)->not->toBeEmpty();
    expect($authSession->refreshToken)->not->toBeNull();
    expect($authSession->refreshToken->sessionId)->toBe($authSession->session->id);
});

it('verifies a valid access token', function () {
    $authSession = $this->sessionManager->create(1, ['roles' => ['user']]);

    $session = $this->sessionManager->verifyAccessToken($authSession->accessToken->value);

    expect($session)->not->toBeNull();
    expect($session->id)->toBe($authSession->session->id);
    expect($session->userId)->toBe(1);
});

it('rejects an invalid access token', function () {
    expect($this->sessionManager->verifyAccessToken('not-a-token'))->toBeNull();
});

it('rejects an access token for a revoked session', function () {
    $authSession = $this->sessionManager->create(1);

    $this->sessionManager->revokeSession($authSession->session->id);

    expect($this->sessionManager->verifyAccessToken($authSession->accessToken->value))->toBeNull();
});

it('refreshes a session and rotates the refresh token', function () {
    $authSession = $this->sessionManager->create(1);
    $firstRefresh = $authSession->refreshToken->value;

    $refreshed = $this->sessionManager->refreshSession($firstRefresh);

    expect($refreshed)->not->toBeNull();
    expect($refreshed->session->id)->toBe($authSession->session->id);
    expect($refreshed->accessToken->value)->not->toBe($authSession->accessToken->value);
    expect($refreshed->refreshToken->value)->not->toBe($firstRefresh);

    expect($this->sessionManager->verifyAccessToken($refreshed->accessToken->value))->not->toBeNull();
});

it('detects refresh token reuse and revokes the family', function () {
    $authSession = $this->sessionManager->create(1);
    $firstRefresh = $authSession->refreshToken->value;

    $refreshed = $this->sessionManager->refreshSession($firstRefresh);
    expect($refreshed)->not->toBeNull();

    $reused = $this->sessionManager->refreshSession($firstRefresh);

    expect($reused)->toBeNull();
    expect($this->sessionManager->verifyAccessToken($refreshed->accessToken->value))->toBeNull();
});

it('adds a claim to an existing session', function () {
    $authSession = $this->sessionManager->create(1);

    $session = $this->sessionManager->addClaim($authSession->session->id, 'mfa_verified', true);

    expect($session)->not->toBeNull();
    expect($session->claims)->toHaveKey('mfa_verified');
    expect($session->claims['mfa_verified'])->toBeTrue();
});
