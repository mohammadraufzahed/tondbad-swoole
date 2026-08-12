<?php

declare(strict_types=1);

use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Support\Context;

beforeEach(function () {
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.database', ':memory:');

    $this->config->set('auth.defaults.guard', 'api_key');
    $this->config->set('auth.defaults.provider', 'api_keys');
    $this->config->set('auth.guards.api_key', [
        'driver' => 'api_key',
        'provider' => 'api_keys',
        'storage_key' => 'api_key',
    ]);
    $this->config->set('auth.providers.api_keys', [
        'driver' => 'api_keys',
        'users_table' => 'users',
        'api_keys_table' => 'api_keys',
        'key_column' => 'key',
        'user_id_column' => 'user_id',
        'expires_at_column' => 'expires_at',
        'auth_identifier' => 'id',
        'auth_password' => 'password',
    ]);

    $this->manager = new DatabaseManager($this->config);
    $this->manager->connection()->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, password TEXT)');
    $this->manager->connection()->getPdo()->exec('CREATE TABLE api_keys (id INTEGER PRIMARY KEY, key TEXT, user_id INTEGER, expires_at TEXT)');

    $this->manager->connection()->table('users')->insert(['id' => 1, 'email' => 'test@example.com', 'password' => password_hash('secret', PASSWORD_BCRYPT)]);
    $this->manager->connection()->table('api_keys')->insert(['key' => 'secret-key-1', 'user_id' => 1, 'expires_at' => null]);
    $this->manager->connection()->table('api_keys')->insert(['key' => 'secret-key-2', 'user_id' => 1, 'expires_at' => null]);
    $this->manager->connection()->table('api_keys')->insert(['key' => 'expired-key', 'user_id' => 1, 'expires_at' => '2000-01-01 00:00:00']);

    $this->container = new Container();
    $this->container->bind(Config::class, $this->config);
    $this->container->bind(DatabaseManager::class, $this->manager);

    $this->auth = new AuthManager($this->container, $this->config, new Context());
});

it('authenticates a user with a valid api key from the api_keys table', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['X-Api-Key' => 'secret-key-2'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->check())->toBeTrue();
    expect($this->auth->user())->toBeInstanceOf(GenericUser::class);
    expect($this->auth->user()?->get('email'))->toBe('test@example.com');
});

it('rejects an expired api key', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['X-Api-Key' => 'expired-key'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guest())->toBeTrue();
});

it('rejects a missing api key', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guest())->toBeTrue();
});
