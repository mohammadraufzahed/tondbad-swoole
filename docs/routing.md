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

## Parameter constraints

```php
use TondbadSwoole\Core\Route\Route;

$route->get('/users/{id}', [UserController::class, 'show'])->where('id', '[0-9]+');
$route->get('/users/{id}', [UserController::class, 'show'])->whereNumber('id');
$route->get('/files/{uuid}', [FileController::class, 'show'])->whereUuid('uuid');
```

Global patterns apply to every route in the registrar:

```php
$route->pattern('id', '[0-9]+');
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

### Fluent groups

```php
$route
    ->prefix('api/v1')
    ->middleware([AuthMiddleware::class, JsonMiddleware::class])
    ->name('api.v1.')
    ->namespace('App\Http\Controllers')
    ->where('id', '[0-9]+')
    ->group(function (Route $route) {
        $route->get('/users/{id}', [UserController::class, 'show'], [], 'users.show');
    });
```

## Resource routes

```php
$route->resource('posts', PostController::class);
$route->apiResource('posts', PostController::class); // excludes create/edit
```

Options control the generated routes:

```php
$route->resource('posts', PostController::class, ['only' => ['index', 'show']]);
$route->resource('posts', PostController::class, ['except' => ['create', 'edit']]);
```

Nested resources are supported:

```php
$route->resource('posts.comments', CommentController::class);
```

Override the route parameter name if singularization is ambiguous:

```php
$route->resource('articles', ArticleController::class, ['parameters' => ['articles' => 'article']]);
```

## Middleware groups

Define reusable groups at runtime:

```php
$route->middlewareGroup('web', [SessionMiddleware::class, CsrfMiddleware::class]);
$route->middlewareGroup('api', [JsonMiddleware::class, ThrottleMiddleware::class]);

$route->get('/profile', [ProfileController::class, 'show'])->middleware(['web', 'AuthMiddleware']);

$route->middleware(['api'])->group(function (Route $route) {
    $route->get('/users', [UserController::class, 'index']);
});
```

You can also define them in `config/middleware.php`:

```php
<?php

declare(strict_types=1);

return [
    'web' => [\TondbadSwoole\Http\Middleware\SessionMiddleware::class],
    'api' => [\TondbadSwoole\Http\Middleware\ThrottleMiddleware::class],
];
```

Route-level rate limiting is available via the `throttle:max,window` shorthand:

```php
$route->get('/api/expensive', [ApiController::class, 'index'])->middleware(['throttle:60,1']);
```

## Named routes

```php
$route->get('/users/{user}', [UserController::class, 'show'], [], 'users.show');
```

Generate a URL:

```php
$url = app()->container->make(Route::class)->url('users.show', ['user' => 5]);
// /users/5
```

Extra parameters become a query string:

```php
$url = app()->container->make(Route::class)->url('users.show', ['user' => 5, 'tab' => 'profile']);
// /users/5?tab=profile
```

Generate an absolute URL:

```php
$url = app()->container->make(Route::class)->url('users.show', ['user' => 5], false);
// https://tondbad.dev/users/5
```

## Redirects and fallbacks

```php
$route->redirect('/old', '/new', 301);

$route->fallback(function (Request $request, Response $response) {
    $response->status(404)->json(['error' => 'Not found']);
});
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

Controllers can define routes with attributes:

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

### NestJS-style controller and method attributes

```php
use TondbadSwoole\Routing\Attributes\Controller;
use TondbadSwoole\Routing\Attributes\Get;
use TondbadSwoole\Routing\Attributes\Post;

#[Controller('/users')]
class UserController
{
    #[Get]
    public function index(Request $request, Response $response): void
    {
        $response->json(['users' => []]);
    }

    #[Post]
    public function store(Request $request, Response $response): void
    {
        $response->status(201)->json(['created' => true]);
    }

    #[Get('{id}', name: 'users.show')]
    public function show(Request $request, Response $response): void
    {
    }
}
```

### Parameter attributes

```php
use TondbadSwoole\Routing\Attributes\Body;
use TondbadSwoole\Routing\Attributes\Header;
use TondbadSwoole\Routing\Attributes\Param;
use TondbadSwoole\Routing\Attributes\Query;
use TondbadSwoole\Routing\Attributes\Req;
use TondbadSwoole\Routing\Attributes\Res;

class UserController
{
    #[Get('/users/{id}')]
    public function show(#[Param('id')] int $id, Response $response): void
    {
        $response->json(['id' => $id]);
    }

    #[Get('/search')]
    public function search(#[Query('q')] string $query, Response $response): void
    {
        $response->json(['q' => $query]);
    }

    public function store(#[Body] array $data, Response $response): void
    {
        $response->json($data);
    }

    public function track(#[Header('x-request-id')] string $requestId, Response $response): void
    {
        $response->json(['request_id' => $requestId]);
    }
}
```

## Guards

Guards run before the handler and may reject a request:

```php
use TondbadSwoole\Routing\Attributes\Guard;
use TondbadSwoole\Routing\Contracts\Guard as GuardContract;

class AdminGuard implements GuardContract
{
    public function can(Request $request): bool
    {
        return auth()->user()?->is_admin === true;
    }
}

#[Guard(AdminGuard::class)]
class AdminController
{
    #[Get('/admin/dashboard')]
    public function index(): void
    {
    }
}
```

## Pipes

Pipes transform parameter values before they reach the handler:

```php
use TondbadSwoole\Routing\Attributes\Pipe;
use TondbadSwoole\Routing\Contracts\Pipe as PipeContract;

class TrimPipe implements PipeContract
{
    public function transform(mixed $value, ?\ReflectionType $type = null): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }
}

class UserController
{
    #[Get('/users/{name}')]
    public function show(#[Param('name'), Pipe(TrimPipe::class)] string $name): void
    {
    }
}
```

## Interceptors

Interceptors wrap the handler invocation:

```php
use TondbadSwoole\Routing\Attributes\Interceptor;
use TondbadSwoole\Routing\Contracts\Interceptor as InterceptorContract;

class LogInterceptor implements InterceptorContract
{
    public function intercept(Request $request, Response $response, callable $next): mixed
    {
        $start = microtime(true);
        $result = $next();
        $duration = microtime(true) - $start;

        return $result;
    }
}

#[Interceptor(LogInterceptor::class)]
class UserController
{
    #[Get('/users')]
    public function index(): void
    {
    }
}
```

## URL helper

The `route()` helper is a shortcut for `Route::url()`:

```php
$url = route('users.show', ['user' => 5]);              // /users/5
$url = route('users.show', ['user' => 5], true);        // https://tondbad.dev/users/5
```

Signed URLs use the `app.key` configuration value and add `signature` (and optionally `expires`) query parameters:

```php
$signed = app()->container->make(Route::class)->signedUrl('users.show', ['user' => 5]);
$signed = signedRoute('users.show', ['user' => 5], new DateTimeImmutable('+30 minutes'));
```

Validate a signed request with the `ValidateSignature` middleware:

```php
$route->get('/users/{user}/activate', [UserController::class, 'activate'])
    ->middleware([\TondbadSwoole\Http\Middleware\ValidateSignature::class]);
```

## File-based route discovery

Optionally register routes by dropping PHP files in `routes/http/` (or the path configured in `config/routes.php` under `routes.file_routes.path`). Enable it with:

```php
// config/routes.php
return [
    'http' => 'routes/http.php',
    'file_routes' => ['enabled' => true, 'path' => 'routes/http'],
];
```

```
routes/http/
├── index.php          -> GET /
├── users.php          -> GET /users
├── users/[id].php     -> GET /users/{id}
├── docs/[...slug].php -> GET /docs/{slug*}
└── (api)/
    ├── _middleware.php -> middleware for all sibling routes
    └── users.php       -> GET /users
```

Each file returns a callable or an array of HTTP method strings mapped to callables:

```php
<?php

// routes/http/users/[id].php
return [
    'GET'    => function (Request $request, Response $response, int $id): void {
        $response->json(['id' => $id]);
    },
    'DELETE' => function (Request $request, Response $response, int $id): void {
        $response->status(204)->end();
    },
];
```

`_middleware.php` files in any directory return an array of middleware class names that apply to all sibling routes.

## Route caching

Compile routes for production:

```bash
php bin/tondbad route:cache
```

Clear the framework cache (including the route cache):

```bash
php bin/tondbad cache:clear
```

Cached routes are stored in `storage/cache/routes.cache.php` and take precedence over runtime definitions. The cache path is configurable via `config/app.php` (`route_cache_file`).

`route:cache` deletes any existing cache file before rebuilding, so route changes are reflected after a server restart. Use `cache:clear` to remove the cache and return to uncached routing.
