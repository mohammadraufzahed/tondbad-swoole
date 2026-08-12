<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Core\Cache\InMemoryCache;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;

beforeEach(function () {
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
        'pool' => [
            'min' => 1,
            'max' => 1,
            'wait_timeout' => 3.0,
        ],
    ]);

    $this->config->set('auth.defaults.guard', 'token');
    $this->config->set('auth.defaults.provider', 'users');
    $this->config->set('auth.guards.token', [
        'driver' => 'token',
        'provider' => 'users',
        'storage_key' => 'api_token',
    ]);
    $this->config->set('auth.providers.users', [
        'driver' => 'database',
        'table' => 'users',
        'auth_identifier' => 'id',
        'auth_password' => 'password',
    ]);

    $this->manager = new DatabaseManager($this->config);
    $this->manager->connection()->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, api_token TEXT, password TEXT)');
    $this->manager->connection()->table('users')->insert([
        'email' => 'test@example.com',
        'api_token' => 'valid-token',
        'password' => password_hash('secret', PASSWORD_BCRYPT),
    ]);

    $this->container = new Container();
    $this->container->bind(Config::class, $this->config);
    $this->container->bind(DatabaseManager::class, $this->manager);
    $this->container->bind(CacheInterface::class, new InMemoryCache(1024, 1000));

    $this->auth = new AuthManager($this->container, $this->config);
});

it('authenticates a user via token guard', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Bearer valid-token'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->check())->toBeTrue();
    expect($this->auth->user())->toBeInstanceOf(GenericUser::class);
    expect($this->auth->id())->toBe(1);
});

it('returns guest for missing token', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guest())->toBeTrue();
    expect($this->auth->user())->toBeNull();
});

it('validates credentials via session guard', function () {
    $this->config->set('auth.defaults.guard', 'session');
    $this->config->set('auth.guards.session', [
        'driver' => 'session',
        'provider' => 'users',
        'session_key' => 'session_id',
        'lifetime' => 7200,
    ]);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->validate(['email' => 'test@example.com', 'password' => 'secret']))->toBeTrue();
    expect($this->auth->validate(['email' => 'test@example.com', 'password' => 'wrong']))->toBeFalse();
});

it('supports custom guards via extend with a closure', function () {
    $this->config->set('auth.guards.custom', [
        'driver' => 'custom',
        'provider' => 'users',
    ]);

    $this->auth->extend('custom', function (\TondbadSwoole\Core\Container $container, \TondbadSwoole\Auth\Contracts\UserProvider $provider, array $config) {
        return new class($provider) implements \TondbadSwoole\Auth\Contracts\Guard {
            public function __construct(private readonly \TondbadSwoole\Auth\Contracts\UserProvider $provider) {}

            public function check(): bool { return true; }
            public function guest(): bool { return false; }
            public function user(): ?\TondbadSwoole\Auth\Contracts\Authenticatable { return null; }
            public function id(): string|int|null { return null; }
            public function setUser(\TondbadSwoole\Auth\Contracts\Authenticatable $user): self { return $this; }
            public function validate(array $credentials = []): bool { return true; }
        };
    });

    expect($this->auth->guard('custom')->check())->toBeTrue();
});

it('supports custom guards via a GuardFactory class', function () {
    $factory = new class() implements \TondbadSwoole\Auth\Contracts\GuardFactory {
        public function create(\TondbadSwoole\Core\Container $container, \TondbadSwoole\Auth\Contracts\UserProvider $provider, array $config, string $name): \TondbadSwoole\Auth\Contracts\Guard
        {
            return new class($provider) implements \TondbadSwoole\Auth\Contracts\Guard {
                public function __construct(private readonly \TondbadSwoole\Auth\Contracts\UserProvider $provider) {}

                public function check(): bool { return true; }
                public function guest(): bool { return false; }
                public function user(): ?\TondbadSwoole\Auth\Contracts\Authenticatable { return null; }
                public function id(): string|int|null { return null; }
                public function setUser(\TondbadSwoole\Auth\Contracts\Authenticatable $user): self { return $this; }
                public function validate(array $credentials = []): bool { return true; }
            };
        }
    };

    $this->config->set('auth.guards.factory', [
        'driver' => get_class($factory),
        'provider' => 'users',
    ]);

    $this->container->bind(get_class($factory), $factory);

    expect($this->auth->guard('factory')->check())->toBeTrue();
});
