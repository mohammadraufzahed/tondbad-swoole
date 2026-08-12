<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Route\HandlerInvoker;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_binding_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->app->config->set('database.default', 'sqlite');
    $this->app->config->set('database.connections.sqlite.database', ':memory:');

    $manager = $this->app->container->make(DatabaseManager::class);
    $manager->connection()->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    $manager->connection()->table('users')->insert(['id' => 1, 'name' => 'Alice']);
    $manager->connection()->table('users')->insert(['id' => 2, 'name' => 'Bob']);

    $this->container = $this->app->container;

    eval('namespace App\\Models; class User extends \\TondbadSwoole\\Database\\Model { protected ?string $table = "users"; public bool $timestamps = false; protected array $guarded = []; }');
});

it('resolves a model from a route parameter', function () {
    $invoker = new HandlerInvoker($this->container);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];
    $request = new Request($swoole);

    $responseSwoole = new OpenSwoole\Http\Response();
    $response = new Response($responseSwoole);

    $captured = null;
    $handler = function (\App\Models\User $user) use (&$captured) {
        $captured = $user;
    };

    $invoker->invoke($handler, $request, $response, ['user' => '2']);

    expect($captured)->not->toBeNull();
    expect($captured->id)->toBe(2);
    expect($captured->name)->toBe('Bob');
});
