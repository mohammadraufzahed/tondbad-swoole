<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Identity\HttpClient;
use TondbadSwoole\Auth\Identity\HttpResponse;
use TondbadSwoole\Auth\Identity\IdentityToken;
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
    $this->config->set('auth.identities.providers.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'authorization_endpoint' => 'https://example.com/oauth/authorize',
        'token_endpoint' => 'https://example.com/oauth/token',
        'userinfo_endpoint' => 'https://example.com/oauth/userinfo',
        'scope' => 'openid email profile',
    ]);

    $this->manager = new DatabaseManager($this->config);
    $pdo = $this->manager->connection()->getPdo();
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password TEXT, name TEXT)');
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
    $pdo->exec('CREATE TABLE identities (
        id INTEGER PRIMARY KEY,
        user_id VARCHAR(255),
        provider VARCHAR(64),
        provider_user_id VARCHAR(255),
        email TEXT,
        name TEXT,
        claims TEXT,
        created_at INTEGER,
        updated_at INTEGER
    )');

    $this->httpClient = new class() implements HttpClient {
        /** @var list<array{url: string, data: array<string, mixed>, headers: array<string, string>}> */
        public array $requests = [];

        public function post(string $url, array $data, array $headers = []): HttpResponse
        {
            $this->requests[] = ['url' => $url, 'data' => $data, 'headers' => $headers];
            $body = json_encode([
                'access_token' => 'access-token',
                'token_type' => 'Bearer',
            ]);

            return new HttpResponse(200, $body, json_decode($body, true) ?: []);
        }

        public function get(string $url, array $headers = []): HttpResponse
        {
            $this->requests[] = ['url' => $url, 'data' => [], 'headers' => $headers];
            $body = json_encode([
                'sub' => 'google-user-123',
                'email' => 'google@example.com',
                'name' => 'Google User',
            ]);

            return new HttpResponse(200, $body, json_decode($body, true) ?: []);
        }
    };

    $container = new Container();
    $container->bind(\TondbadSwoole\Core\Config::class, $this->config);
    $container->bind(DatabaseManager::class, $this->manager);
    $container->bind(\TondbadSwoole\Support\Hash\Contracts\Hasher::class, (new \TondbadSwoole\Support\Hash\HashManager($this->config))->driver());
    $container->bind(HttpClient::class, $this->httpClient);

    $this->context = new Context();
    $this->auth = new AuthManager($container, $this->config, $this->context);
});

it('generates an oidc redirect url with pkce', function () {
    $url = $this->auth->via('google')->redirect('https://app.test/callback');

    expect($url)->toContain('https://example.com/oauth/authorize?');
    expect($url)->toContain('client_id=client-id');
    expect($url)->toContain('response_type=code');
    expect($url)->toContain('code_challenge_method=S256');
    expect($url)->toContain('redirect_uri=' . urlencode('https://app.test/callback'));
    expect($url)->toContain('state=');
    expect($url)->toContain('code_challenge=');
});

it('exchanges code and resolves identity', function () {
    $broker = $this->auth->via('google');
    $url = $broker->redirect('https://app.test/callback');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    $state = $query['state'];

    $token = $broker->callback('auth-code', $state, 'https://app.test/callback');

    expect($token)->toBeInstanceOf(IdentityToken::class);
    expect($token->provider)->toBe('google');
    expect($token->providerUserId)->toBe('google-user-123');
    expect($token->email)->toBe('google@example.com');

    $tokenRequest = $this->httpClient->requests[0];
    expect($tokenRequest['data'])->toHaveKey('code_verifier');
    expect($tokenRequest['data']['code_verifier'])->not->toBeEmpty();
});

it('rejects an invalid oidc state', function () {
    $broker = $this->auth->via('google');
    $broker->redirect('https://app.test/callback');

    $broker->callback('auth-code', 'wrong-state', 'https://app.test/callback');
})->throws(\RuntimeException::class);

it('creates or links a user from an identity token', function () {
    $broker = $this->auth->via('google');
    $url = $broker->redirect('https://app.test/callback');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $token = $broker->callback('auth-code', $query['state'], 'https://app.test/callback');
    $session = $this->auth->handleIdentity($token);

    expect($session->session->userId)->toBe(1);
    expect($this->manager->table('identities')->where('provider_user_id', '=', 'google-user-123')->first())->not->toBeNull();
});
