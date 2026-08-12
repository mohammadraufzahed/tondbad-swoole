# Getting Started

## Requirements

- PHP 8.2+
- `ext-openswoole` for the server runtime
- Composer

## Installation

```bash
composer require mohammadraufzahed/tondbad-swoole
```

## Bootstrap a project

Create a `public/index.php` that boots the application from your base path:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TondbadSwoole\Bootstrap\App;

$app = new App(__DIR__ . '/..');
$app->run();
```

The base path is the root of your project (where `config/`, `routes/`, `app/`, etc. live).

## Project structure

```
my-project/
├── app/
│   ├── Console/Commands/
│   ├── Controllers/
│   ├── Entities/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Middleware/
│   ├── Models/
│   ├── Policies/
│   └── Providers/
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── cache.php
│   ├── cors.php
│   ├── database.php
│   ├── grpc.php
│   ├── hashing.php
│   ├── providers.php
│   ├── queue.php
│   └── routes.php
├── database/
│   └── migrations/
├── routes/
│   ├── http.php
│   └── grpc.php
├── public/
│   ├── index.php
│   └── grpc.php
├── storage/
│   ├── logs/
│   └── cache/
├── tests/
├── composer.json
└── .env
```

## Minimal routes

`routes/http.php`:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

return function (Route $route): void {
    $route->get('/', function (Request $request, Response $response) {
        $response->json(['message' => 'Hello from Tondbād']);
    });
};
```

## Run the server

```bash
php bin/tondbad serve
```

or directly:

```bash
php public/index.php
```

The OpenSwoole HTTP server starts and listens on the configured host/port.
