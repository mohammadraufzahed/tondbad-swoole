<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_config_middleware_groups_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/routes", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");
    file_put_contents("{$this->tmpDir}/config/middleware.php", "<?php\nreturn ['web' => ['AuthMiddleware'], 'api' => ['ThrottleMiddleware']];");
    file_put_contents("{$this->tmpDir}/routes/http.php", "<?php\nreturn function (TondbadSwoole\\Core\\Route\\Route \$route) {\n    \$route->get('/', fn() => 'ok')->middleware(['web']);\n};");

    $this->app = AppFactory::create($this->tmpDir);
});

it('loads named middleware groups from config/middleware.php', function () {
    $route = $this->app->container->make(Route::class);

    $routes = $route->getRoutes();

    expect($routes)->toHaveCount(1);
    expect($routes[0][3])->toBe(['AuthMiddleware']);
});
