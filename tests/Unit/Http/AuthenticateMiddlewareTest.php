<?php

declare(strict_types=1);

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Middleware\Authenticate;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_auth_middleware_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->app->config->set('database.default', 'sqlite');
    $this->app->config->set('database.connections.sqlite.database', ':memory:');

    $manager = $this->app->container->make(DatabaseManager::class);
    $manager->connection()->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, api_token TEXT, password TEXT)');
    $manager->connection()->table('users')->insert([
        'email' => 'test@example.com',
        'api_token' => 'valid-token',
        'password' => password_hash('secret', PASSWORD_BCRYPT),
    ]);

    $this->app->container->make(ContextInterface::class)->clear();
});

it('allows the request when authenticated', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Bearer valid-token'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    $called = false;
    $next = function (Request $req, Response $res) use (&$called) { $called = true; };

    (new Authenticate())->process($request, $response, $next);

    expect($called)->toBeTrue();
});

it('throws when not authenticated', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    (new Authenticate())->process($request, $response, function () {});
})->throws(AuthorizationException::class);

it('uses a specific guard', function () {
    $this->app->config->set('auth.guards.admin', [
        'driver' => 'token',
        'provider' => 'users',
        'storage_key' => 'api_token',
    ]);

    $this->app->container->make(DatabaseManager::class)
        ->connection()->table('users')
        ->insert(['email' => 'admin@example.com', 'api_token' => 'admin-token', 'password' => '']);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Bearer admin-token'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];

    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    $called = false;
    $next = function (Request $req, Response $res) use (&$called) { $called = true; };

    Authenticate::guard('admin')->process($request, $response, $next);

    expect($called)->toBeTrue();
});
