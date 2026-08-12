<?php

declare(strict_types=1);

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\HandlerInvoker;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_controller_auth_test');
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

it('allows a controller method without authenticate attribute', function () {
    $controller = new class() {
        public function index(): string
        {
            return 'ok';
        }
    };

    $invoker = new HandlerInvoker($this->app->container);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    $captured = null;
    $handler = [get_class($controller), 'index'];
    $invoker->invoke($handler, $request, $response, []);

    expect(true)->toBeTrue();
});

it('throws when controller method requires authentication', function () {
    $controller = new class() {
        #[\TondbadSwoole\Http\Attributes\Authenticate]
        public function secret(): string
        {
            return 'hidden';
        }
    };

    $invoker = new HandlerInvoker($this->app->container);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    $invoker->invoke([get_class($controller), 'secret'], $request, $response, []);
})->throws(AuthorizationException::class);

it('allows controller method with valid authentication', function () {
    $controller = new class() {
        #[\TondbadSwoole\Http\Attributes\Authenticate]
        public function secret(): string
        {
            return 'hidden';
        }
    };

    $invoker = new HandlerInvoker($this->app->container);

    $swoole = new OpenSwoole\Http\Request();
    $swoole->header = ['authorization' => 'Bearer valid-token'];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->server = [];
    $swoole->cookie = [];
    $request = new Request($swoole);
    $response = new Response(new OpenSwoole\Http\Response());
    $this->app->container->make(ContextInterface::class)->set('request', $request);

    $invoker->invoke([get_class($controller), 'secret'], $request, $response, []);

    expect(true)->toBeTrue();
});
