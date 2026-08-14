# Controllers, Middleware, and Requests

## Controllers

Controllers are plain PHP classes resolved from the container:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class UserController
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function index(Request $request, Response $response): void
    {
        $response->json($this->users->all());
    }

    public function show(Request $request, Response $response, int $id): void
    {
        $response->json($this->users->find($id));
    }
}
```

Route parameters are injected by name and type:

```php
$route->get('/users/{id}', [UserController::class, 'show']);
```

## Middleware

Implement `MiddlewareInterface`:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use TondbadSwoole\Contracts\MiddlewareInterface;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): void
    {
        $response->header('Access-Control-Allow-Origin', '*');

        $next($request, $response);
    }
}
```

Register global middleware in `config/app.php`:

```php
'middlewares' => [
    \App\Middleware\CorsMiddleware::class,
],
```

Or apply per route/group:

```php
$route->get('/admin', [AdminController::class, 'index'], [AuthMiddleware::class]);

$route->group('/admin', function (Route $route) {
    // routes
}, [AuthMiddleware::class]);
```

## Request validation

Validate inside a controller:

```php
public function store(Request $request, Response $response): void
{
    $data = $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:8',
    ]);

    // $data contains validated, safe input
}
```

## Form requests

Create a `FormRequest` class for reusable validation:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use TondbadSwoole\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ];
    }
}
```

Use it as a controller parameter:

```php
public function store(StoreUserRequest $request, Response $response): void
{
    $data = $request->validated();

    $response->json(['created' => true]);
}
```

`FormRequest` validates on construction and throws `ValidationException` on failure.

## Responses

The `Response` object wraps `OpenSwoole\Http\Response`:

```php
$response->json(['users' => []]);
$response->html('<h1>Hello</h1>');
$response->text('ok');
$response->redirect('/login');
$response->status(404)->end('Not found');
```

For raw OpenSwoole methods, the object uses `__call` to forward them:

```php
$response->setCookie('name', 'value');
```
