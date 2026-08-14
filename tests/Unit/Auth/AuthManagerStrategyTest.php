<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\SessionManager;
use TondbadSwoole\Auth\SessionStores\DatabaseSessionStore;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Support\Context;

beforeEach(function () {
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.database', ':memory:');
    $this->config->set('app.key', 'test-secret-key-at-least-32-characters');
    $this->config->set('auth.access_token_ttl', 60);
    $this->config->set('auth.refresh_token_ttl', 120);
    $this->config->set('auth.defaults.guard', 'session');
    $this->config->set('auth.defaults.provider', 'users');
    $this->config->set('auth.guards.session', [
        'driver' => 'session',
        'provider' => 'users',
        'session_key' => 'session_id',
        'mode' => 'stateful',
        'access_ttl' => 60,
        'refresh_ttl' => 120,
        'cookie' => ['http_only' => true, 'same_site' => 'lax', 'secure' => false, 'path' => '/'],
    ]);
    $this->config->set('auth.providers.users', [
        'driver' => 'database',
        'table' => 'users',
        'auth_identifier' => 'id',
        'auth_password' => 'password',
    ]);

    $this->manager = new DatabaseManager($this->config);
    $pdo = $this->manager->connection()->getPdo();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password TEXT)');
    $pdo->exec('CREATE TABLE sessions (
        id VARCHAR(36) PRIMARY KEY,
        user_id VARCHAR(255),
        claims TEXT,
        anti_csrf VARCHAR(64),
        device VARCHAR(255),
        family VARCHAR(36),
        status VARCHAR(20),
        expires_at INTEGER,
        created_at INTEGER
    )');
    $pdo->exec('CREATE TABLE refresh_tokens (
        id INTEGER PRIMARY KEY,
        session_id VARCHAR(36),
        family VARCHAR(36),
        parent INTEGER,
        token_hash VARCHAR(64) UNIQUE,
        used_at INTEGER,
        revoked INTEGER DEFAULT 0,
        expires_at INTEGER,
        created_at INTEGER
    )');

    $container = new Container();
    $container->bind(\TondbadSwoole\Core\Config::class, $this->config);
    $container->bind(DatabaseManager::class, $this->manager);
    $container->bind(\TondbadSwoole\Support\Hash\Contracts\Hasher::class, (new \TondbadSwoole\Support\Hash\HashManager($this->config))->driver());

    $this->container = $container;
    $this->context = new Context();

    $this->auth = new AuthManager($this->container, $this->config, $this->context);
});

it('signs up a user with the email strategy', function () {
    $session = $this->auth->signUp('email', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    expect($session)->not->toBeNull();
    expect($session->session->userId)->toBe(1);
    expect($session->accessToken->value)->not->toBeEmpty();
    expect($session->refreshToken)->not->toBeNull();
});

it('signs in a user with the email strategy', function () {
    $this->manager->table('users')->insert([
        'email' => 'user@example.com',
        'password' => password_hash('password123', PASSWORD_BCRYPT),
    ]);

    $session = $this->auth->signIn('email', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    expect($session)->not->toBeNull();
    expect($session->session->userId)->toBe(1);
});

it('returns null for invalid email credentials', function () {
    $this->manager->table('users')->insert([
        'email' => 'user@example.com',
        'password' => password_hash('password123', PASSWORD_BCRYPT),
    ]);

    $session = $this->auth->signIn('email', [
        'email' => 'user@example.com',
        'password' => 'wrongpassword',
    ]);

    expect($session)->toBeNull();
});

it('issues a stateless api token', function () {
    $this->manager->table('users')->insert([
        'email' => 'api@example.com',
        'password' => password_hash('password123', PASSWORD_BCRYPT),
    ]);

    $token = $this->auth->issueApiToken(1, ['scopes' => ['read']]);

    expect($token->value)->not->toBeEmpty();

    $session = $this->auth->sessionManager()->verifyAccessToken($token->value);

    expect($session)->not->toBeNull();
    expect($session->userId)->toBe(1);
    expect($session->claims)->toBe(['scopes' => ['read']]);
    expect($session->mode)->toBe('stateless');
});

it('revokes a session', function () {
    $session = $this->auth->signUp('email', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $this->auth->revokeSession($session->session->id);

    expect($this->auth->sessionManager()->verifyAccessToken($session->accessToken->value))->toBeNull();
});
