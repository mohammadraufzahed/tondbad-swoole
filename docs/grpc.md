# gRPC

Tondbād can boot an OpenSwoole gRPC server that shares the same container and providers as the HTTP server.

## Bootstrapping the gRPC server

Create `public/grpc.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TondbadSwoole\Bootstrap\App;

$app = new App(__DIR__ . '/..');
$app->run();
```

Set the app type to `grpc` in `config/app.php` or via `APP_TYPE=grpc`.

## Defining services

Place `.proto` files in `protos/` and generate PHP classes with `protoc` or the bundled `composer compile-proto` script. Generated classes live in `generated/` and must be autoloaded from the consumer's `composer.json`, for example:

```json
"autoload": {
    "psr-4": {
        "TondbadExample\\": "generated/TondbadExample/",
        "GPBMetadata\\": "generated/GPBMetadata/"
    }
}
```

## Registering gRPC services

Register service classes in `config/grpc.php`:

```php
<?php

declare(strict_types=1);

return [
    'services' => [
        \App\Services\GreeterService::class,
    ],
];
```

A service class is resolved from the container, so constructor dependencies are auto-wired:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use TondbadExample\HelloRequest;
use TondbadExample\HelloResponse;

class GreeterService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    public function sayHello(HelloRequest $request): HelloResponse
    {
        $reply = new HelloResponse();
        $reply->setMessage('Hello, ' . $request->getName());

        return $reply;
    }
}
```

## Middleware

Register gRPC middleware in `config/grpc.php`:

```php
'middlewares' => [
    \App\Grpc\Middleware\TraceMiddleware::class,
],
```

## gRPC route adapter

You can also route gRPC calls through the same `Route` pipeline as HTTP, sharing middleware, guards, pipes, and interceptors. When `app.type` is `grpc`, `routes/grpc.php` is loaded and each gRPC method is dispatched as a `POST` request to `/{service}/{method}`.

```php
// routes/grpc.php
<?php

declare(strict_types=1);

return function (TondbadSwoole\Core\Route\Route $route): void {
    $route->post('/greeter.Greeter/SayHello', function (Request $request, Response $response): void {
        $response->json(['message' => 'Hello, ' . $request->input('name')]);
    });
};
```

For `application/grpc+json` requests, the JSON payload is parsed and made available through `$request->input()` / `$request->all()`. For `application/grpc+proto`, the raw message body is accessible via `$request->getSwooleRequest()->rawContent()`. The framework middleware pipeline, guards, pipes, and interceptors all work for gRPC routes, and the HTTP response status is mapped to a gRPC status code in the response trailers.

## Running the server

```bash
php bin/tondbad serve:grpc --host=0.0.0.0 --port=9502
```

## Configuration

`config/app.php`:

```php
<?php

declare(strict_types=1);

return [
    'type' => $env->get('app.type', 'grpc'),

    'grpc' => [
        'host' => $env->get('app.grpc.host', '0.0.0.0'),
        'port' => $env->get('app.grpc.port', 9502),
        'mode' => $env->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
        'sock_type' => $env->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
        'settings' => [
            'worker_num' => (int) $env->get('app.grpc.settings.worker_num', 4),
        ],
    ],
];
```

`config/grpc.php`:

```php
<?php

declare(strict_types=1);

use OpenSwoole\GRPC\Middleware\LoggingMiddleware;
use OpenSwoole\GRPC\Middleware\TraceMiddleware;
use TondbadSwoole\Core\GRPC\Middlewares\GrpcCorsMiddleware;

return [
    'services' => [
        \App\Services\GreeterService::class,
    ],
    'middlewares' => [
        LoggingMiddleware::class,
        TraceMiddleware::class,
        GrpcCorsMiddleware::class,
    ],
];
```

The gRPC server shares the same container, config, and providers as the HTTP server.
