<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_resource_routes_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'http'];");

    $this->app = AppFactory::create($this->tmpDir);
    $this->route = $this->app->container->make(Route::class);
});

it('generates resource routes', function () {
    $this->route->resource('posts', 'PostController');

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/posts');
    expect($paths)->toContain('/posts/create');
    expect($paths)->toContain('/posts/{post}');
    expect($paths)->toContain('/posts/{post}/edit');
});

it('names resource routes', function () {
    $this->route->resource('posts', 'PostController');

    expect($this->route->has('posts.index'))->toBeTrue();
    expect($this->route->has('posts.show'))->toBeTrue();
    expect($this->route->has('posts.store'))->toBeTrue();
    expect($this->route->url('posts.show', ['post' => 5]))->toBe('/posts/5');
});

it('excludes create and edit for api resources', function () {
    $this->route->apiResource('posts', 'PostController');

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/posts');
    expect($paths)->not->toContain('/posts/create');
    expect($paths)->not->toContain('/posts/{post}/edit');
    expect($paths)->toContain('/posts/{post}');
});

it('supports nested resources', function () {
    $this->route->resource('posts.comments', 'CommentController');

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/posts/{post}/comments');
    expect($paths)->toContain('/posts/{post}/comments/{comment}');
    expect($this->route->has('posts.comments.show'))->toBeTrue();
    expect($this->route->url('posts.comments.show', ['post' => 1, 'comment' => 2]))->toBe('/posts/1/comments/2');
});

it('respects resource only and except options', function () {
    $this->route->resource('posts', 'PostController', ['only' => ['index', 'show']]);

    $routes = $this->route->getRoutes();

    expect(count($routes))->toBe(2);
    expect(array_column($routes, 1))->toContain('/posts');
    expect(array_column($routes, 1))->toContain('/posts/{post}');

    $this->route = $this->app->container->make(Route::class);
    $this->route->resource('comments', 'CommentController', ['except' => ['create', 'edit']]);

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->not->toContain('/comments/create');
    expect($paths)->not->toContain('/comments/{comment}/edit');
    expect($paths)->toContain('/comments/{comment}');
});

it('singularizes words ending in les and ies', function () {
    $this->route->resource('articles', 'ArticleController');
    $this->route->resource('categories', 'CategoryController');

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/articles/{article}');
    expect($paths)->toContain('/categories/{category}');
});

it('allows overriding the resource parameter name', function () {
    $this->route->resource('articles', 'ArticleController', ['parameters' => ['articles' => 'slug']]);

    $routes = $this->route->getRoutes();
    $paths = array_column($routes, 1);

    expect($paths)->toContain('/articles/{slug}');
});
