<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Auth\Mfa\EmailOtpFactor;
use TondbadSwoole\Auth\Mfa\MfaManager;
use TondbadSwoole\Auth\Mfa\TotpFactor;
use TondbadSwoole\Core\Config;
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
    $pdo->exec('CREATE TABLE mfa_factors (
        id INTEGER PRIMARY KEY,
        user_id VARCHAR(255),
        type VARCHAR(32),
        config TEXT,
        enabled INTEGER DEFAULT 1,
        created_at INTEGER,
        updated_at INTEGER
    )');

    $container = new Container();
    $container->bind(\TondbadSwoole\Core\Config::class, $this->config);
    $container->bind(DatabaseManager::class, $this->manager);
    $container->bind(\TondbadSwoole\Support\Hash\Contracts\Hasher::class, (new \TondbadSwoole\Support\Hash\HashManager($this->config))->driver());

    $context = new Context();
    $auth = new AuthManager($container, $this->config, $context);

    $this->mfa = new MfaManager($this->manager, $this->config, $auth);
    $this->mfa->registerFactor(new TotpFactor($this->config, $this->manager));
    $this->mfa->registerFactor(new EmailOtpFactor($this->manager));

    $this->user = new GenericUser('users', ['id' => 1, 'email' => 'test@example.com', 'password' => ''], 'id', 'password');
});

it('sets up and verifies a totp factor', function () {
    $challenge = $this->mfa->challenge($this->user, 'totp');

    expect($challenge)->toHaveKey('secret');
    expect($challenge)->toHaveKey('qr_uri');
    expect($this->mfa->hasFactor($this->user, 'totp'))->toBeTrue();

    $code = totpCode($challenge['secret']);

    expect($this->mfa->verify($this->user, 'totp', (string) $code))->toBeTrue();
});

it('sets up and verifies an email otp factor', function () {
    $challenge = $this->mfa->challenge($this->user, 'email');

    expect($challenge)->toHaveKey('code');
    expect($challenge)->toHaveKey('expires_at');

    expect($this->mfa->verify($this->user, 'email', $challenge['code']))->toBeTrue();
    expect($this->mfa->verify($this->user, 'email', $challenge['code']))->toBeFalse();
});

it('marks the session as mfa verified', function () {
    $container = new Container();
    $container->bind(Config::class, $this->config);
    $container->bind(DatabaseManager::class, $this->manager);
    $container->bind(\TondbadSwoole\Support\Hash\Contracts\Hasher::class, (new \TondbadSwoole\Support\Hash\HashManager($this->config))->driver());

    $context = new Context();
    $auth = new AuthManager($container, $this->config, $context);
    $auth->login($this->user, 'session');

    $mfa = new MfaManager($this->manager, $this->config, $auth);
    $mfa->registerFactor(new EmailOtpFactor($this->manager));

    $challenge = $mfa->challenge($this->user, 'email');
    $mfa->verify($this->user, 'email', $challenge['code']);

    expect($auth->session()?->claims['mfa_verified'] ?? false)->toBeTrue();
});

function totpCode(string $secret, ?int $time = null): int
{
    $timestamp = $time ?? time();
    $counter = (int) floor($timestamp / 30);
    $decoded = \TondbadSwoole\Auth\Mfa\Base32::decode($secret);
    $counterBytes = pack('N*', 0, $counter);
    $hash = hash_hmac('sha1', $counterBytes, $decoded, true);
    $offset = ord($hash[-1]) & 0x0f;
    $binary = (unpack('N', substr($hash, $offset, 4))[1] ?? 0) & 0x7fffffff;

    return $binary % 1000000;
}
