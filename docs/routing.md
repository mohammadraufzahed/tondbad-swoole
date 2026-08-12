# Routing

Tondbād uses `FastRoute` and exposes routes through a `Route` instance passed into your `routes/http.php` file.

## Route files

`routes/http.php` must return a callable that receives the `Route` registrar:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

return function (Route $route): void {
    $route->get('/', function (Request $request, Response $response) {
        $response->json(['message' => 'ok']);
    });
};
```

## Basic routes

```php
$route->get('/', [HomeController::class, 'index']);
$route->post('/users', [UserController::class, 'store']);
$route->put('/users/{id}', [UserController::class, 'update']);
$route->delete('/users/{id}', [UserController::class, 'destroy']);
$route->patch('/users/{id}', [UserController::class, 'patch']);
```

## Route parameters

```php
$route->get('/users/{id}', function (Request $request, Response $response, int $id) {
    $response->json(['id' => $id]);
});
```

Optional segments use FastRoute bracket syntax:

```php
$route->get('/hello[/{name}]', function (Request $request, Response $response, ?string $name = '') {
    $response->html('Hello ' . htmlspecialchars($name ?? ''));
});
```

## Route groups

```php
$route->group('', function (Route $route) {
    $route->get('/dashboard', [DashboardController::class, 'index']);

    $route->group('/admin', function (Route $route) {
        $route->get('/users', [AdminController::class, 'users']);
    });
}, [AuthMiddleware::class]);
```

`group()` takes a prefix, a callback, and an optional array of middleware classes. Middleware can be class names implementing `MiddlewareInterface` or invokable closures.

## Named routes

```php
$route->get('/users/{user}', [UserController::class, 'show'], [], 'users.show');
```

Generate a URL:

```php
$url = app()->container->make(Route::class)->url('users.show', ['user' => 5]);
// /users/5
```

## Route model binding

If a route parameter is typed with a class that implements `UrlRoutable`, the framework resolves it from the database:

```php
use TondbadSwoole\Routing\Contracts\UrlRoutable;

class Post extends Model implements UrlRoutable
{
    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static
    {
        return $this->where($field ?? $this->getKeyName(), $value)->first();
    }
}

$route->get('/posts/{post}', function (Post $post, Response $response) {
    $response->json($post->toArray());
});
```

## Annotated routes

Controllers can also define routes with attributes:

```php
use TondbadSwoole\Core\Route\Attributes\Endpoint;

class UserController
{
    #[Endpoint('GET', '/users')]
    public function index(Request $request, Response $response): void
    {
        $response->json(['users' => []]);
    }
}
```

## Route caching

Compile routes for production:

```bash
php bin/tondbad route:cache
```

Clear the framework cache (including the route cache):

```bash
php bin/tondbad cache:clear
```

Cached routes are stored in `storage/framework/route-cache.php` and take precedence over runtime definitions. The cache path is configurable via `config/routes.php`.
