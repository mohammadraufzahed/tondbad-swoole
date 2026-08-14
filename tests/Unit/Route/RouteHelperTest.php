<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_route_helper_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('generates a relative url with the route helper', function () {
    $this->route->get('/users/{user}', fn () => 'ok', [], 'users.show');

    expect(route('users.show', ['user' => 5]))->toBe('/users/5');
});

it('generates an absolute url with the route helper', function () {
    $this->app->config->set('app.url_scheme', 'https');
    $this->app->config->set('app.url_host', 'tondbad.dev');

    $this->route = $this->app->container->make(Route::class);
    $this->route->get('/home', fn () => 'ok', [], 'home');

    expect(route('home', [], true))->toBe('https://tondbad.dev/home');
});

it('appends extra parameters as query string with the route helper', function () {
    $this->route->get('/users/{user}', fn () => 'ok', [], 'users.show');

    expect(route('users.show', ['user' => 5, 'tab' => 'profile']))->toBe('/users/5?tab=profile');
});
