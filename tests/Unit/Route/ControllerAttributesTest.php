<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Routing\Attributes\Controller;
use TondbadSwoole\Routing\Attributes\Get;
use TondbadSwoole\Routing\Attributes\Post;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_controller_attributes_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('registers controller attribute routes', function () {
    $controller = new #[Controller('/users')] class {
        #[Get]
        public function index(): void {}

        #[Get('{id}', name: 'users.show')]
        public function show(): void {}

        #[Post]
        public function store(): void {}
    };

    $this->route->registerAnnotatedRoutes([get_class($controller)]);

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/users');
    expect($paths)->toContain('/users/{id}');
    expect($paths)->toContain('/users'); // POST
    expect($this->route->has('users.show'))->toBeTrue();
});

it('keeps legacy endpoint attributes working', function () {
    $controller = new class {
        #[\TondbadSwoole\Core\Route\Attributes\Endpoint('GET', '/legacy')]
        public function index(): void {}
    };

    $this->route->registerAnnotatedRoutes([get_class($controller)]);

    $routes = $this->route->getRoutes();

    expect(array_column($routes, 1))->toContain('/legacy');
});

it('combines controller and endpoint attributes', function () {
    $controller = new #[Controller('/api')] class {
        #[\TondbadSwoole\Core\Route\Attributes\Endpoint('GET', '/status')]
        public function status(): void {}
    };

    $this->route->registerAnnotatedRoutes([get_class($controller)]);

    $routes = $this->route->getRoutes();

    expect(array_column($routes, 1))->toContain('/api/status');
});
