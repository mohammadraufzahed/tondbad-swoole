<?php

declare(strict_types=1);

use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Middleware\VerifyCsrfToken;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
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

    $container = new Container();
    $container->bind(\TondbadSwoole\Core\Config::class, $this->config);
    $container->bind(DatabaseManager::class, $this->manager);
    $container->bind(\TondbadSwoole\Support\Hash\Contracts\Hasher::class, (new \TondbadSwoole\Support\Hash\HashManager($this->config))->driver());

    $this->context = new Context();
    $this->auth = new AuthManager($container, $this->config, $this->context);

    $container->bind(\TondbadSwoole\Contracts\ContextInterface::class, $this->context);
    $container->bind(AuthManager::class, $this->auth);

    $app = (new ReflectionClass(App::class))->newInstanceWithoutConstructor();
    setCsrfAppInstance($app, $container);

    $this->user = new GenericUser('users', ['id' => 1, 'email' => 'test@example.com', 'password' => ''], 'id', 'password');
});

afterEach(function () {
    setCsrfAppInstance(null, new Container());
});

function setCsrfAppInstance(?App $app, Container $container): void
{
    $reflection = new ReflectionClass(App::class);
    $property = $reflection->getProperty('instance');
    $property->setAccessible(true);
    $property->setValue(null, $app);

    if ($app !== null) {
        $p = $reflection->getProperty('container');
        $p->setAccessible(true);
        $p->setValue($app, $container);

        $c = $reflection->getProperty('config');
        $c->setAccessible(true);
        $c->setValue($app, $container->make(\TondbadSwoole\Core\Config::class));
    }
}

it('allows safe methods without csrf token', function () {
    $middleware = new VerifyCsrfToken();
    $swooleReq = new SwooleRequest();
    $swooleReq->server = ['request_method' => 'GET'];
    $swooleRes = new SwooleResponse();

    $request = new Request($swooleReq);
    $response = new Response($swooleRes);

    $called = false;
    $middleware->process($request, $response, function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeTrue();
});

it('rejects state-changing requests without a valid csrf token', function () {
    $swoole = new SwooleRequest();
    $swoole->server = ['request_method' => 'POST'];
    $swoole->header = [];

    $request = new Request($swoole);
    $this->context->set('request', $request);
    $this->auth->login($this->user, 'session');

    $middleware = new VerifyCsrfToken();
    $response = new Response(new SwooleResponse());

    $middleware->process($request, $response, function (): void {
        throw new \RuntimeException('Should not reach next.');
    });
})->throws(AuthorizationException::class);

it('allows state-changing requests with a valid csrf token', function () {
    $swoole = new SwooleRequest();
    $swoole->server = ['request_method' => 'POST'];

    $request = new Request($swoole);
    $this->context->set('request', $request);
    $session = $this->auth->login($this->user, 'session');

    $swoole->header = ['x-csrf-token' => $session->session->antiCsrf];

    $middleware = new VerifyCsrfToken();
    $response = new Response(new SwooleResponse());

    $called = false;
    $middleware->process($request, $response, function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeTrue();
});
