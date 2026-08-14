<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Validation\Schema;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_schema_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);

    $this->route = $this->app->routes();
});

it('validates route parameters with a schema', function () {
    $captured = null;

    $this->route->get('/users/{id}', function (int $id) use (&$captured): void {
        $captured = $id;
    })
        ->whereSchema('id', Schema::int()->gte(1));

    $request = new OpenSwoole\Http\Request();
    $request->server = ['request_method' => 'GET', 'request_uri' => '/users/5'];
    $request->get = [];
    $request->post = [];
    $request->header = [];
    $request->cookie = [];

    $response = new OpenSwoole\Http\Response();

    $this->route->dispatch($request, $response);

    expect($captured)->toBe(5);
});

it('validates request data with a schema', function () {
    $swoole = new OpenSwoole\Http\Request();
    $swoole->get = [];
    $swoole->post = ['email' => 'test@example.com', 'age' => '25'];
    $swoole->server = [];
    $swoole->header = [];
    $swoole->cookie = [];

    $request = new Request($swoole);

    $schema = Schema::object([
        'email' => Schema::string()->email(),
        'age' => Schema::int()->coerce()->gte(18),
    ])->lax();

    $validated = $request->validateSchema($schema);

    expect($validated)->toBe(['email' => 'test@example.com', 'age' => 25]);
});
