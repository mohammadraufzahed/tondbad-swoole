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
    $this->config->set('auth.defaults.provider', 'users');
    $this->config->set('auth.guards.api_key', [
        'driver' => 'api_key',
        'provider' => 'users',
        'storage_key' => 'api_key',
    ]);
    $this->config->set('auth.guards.basic', [
        'driver' => 'basic',
        'provider' => 'users',
        'username_key' => 'email',
    ]);
    $this->config->set('auth.providers.users', [
        'driver' => 'database',
        'table' => 'users',
        'auth_identifier' => 'id',
        'auth_password' => 'password',
    ]);

    $this->manager = new DatabaseManager($this->config);
    $this->manager->connection()->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, api_key TEXT, password TEXT)');
    $this->manager->connection()->table('users')->insert([
        'email' => 'test@example.com',
        'api_key' => 'secret-api-key',
        'password' => password_hash('secret', PASSWORD_BCRYPT),
    ]);

    $this->container = new Container();
    $this->container->bind(Config::class, $this->config);
    $this->container->bind(DatabaseManager::class, $this->manager);

    $this->auth = new AuthManager($this->container, $this->config, new Context());
});

it('authenticates via api key header', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['X-Api-Key' => 'secret-api-key'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guard('api_key')->check())->toBeTrue();
    expect($this->auth->guard('api_key')->user())->toBeInstanceOf(GenericUser::class);
});

it('authenticates via api key query parameter', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = ['api_key' => 'secret-api-key'];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guard('api_key')->check())->toBeTrue();
});

it('authenticates via basic auth', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Basic ' . base64_encode('test@example.com:secret')];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guard('basic')->check())->toBeTrue();
    expect($this->auth->guard('basic')->user())->toBeInstanceOf(GenericUser::class);
});

it('rejects invalid basic auth credentials', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Basic ' . base64_encode('test@example.com:wrong')];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $this->auth->setRequest($request);

    expect($this->auth->guard('basic')->guest())->toBeTrue();
});
