# Testing

Tondbād uses [Pest](https://pestphp.com/) v5 for testing.

> Pest 5 and the framework test suite require **PHP 8.4+**.

## Running tests

```bash
composer test
```

## Test configuration

`tests/Pest.php` configures the base test case for the suite:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Tests\Unit\TestCase;

pest()->extend(TestCase::class)->in('Unit');
```

`phpunit.xml` provides PHPUnit 13 configuration and the test suite uses an in-memory SQLite database by default.

## Writing a test

```php
<?php

declare(strict_types=1);

it('creates a user', function () {
    $user = User::create([
        'name' => 'Ava',
        'email' => 'ava@example.com',
    ]);

    expect($user->id)->toBeInt();
    expect(User::find($user->id))->not->toBeNull();
});
```

## Test case setup

`TestCase` boots the application and provides access to the container, config, and database:

```php
it('resolves a service', function () {
    $service = app()->container->make(MyService::class);

    expect($service)->toBeInstanceOf(MyService::class);
});
```

## Database testing

Use an in-memory SQLite database for fast, isolated tests:

```php
beforeEach(function () {
    db('sqlite')->statement('create table if not exists users (id integer primary key, name text)');
});
```

The framework is designed so each test can instantiate a fresh `App` with a clean `Context`.

## HTTP testing

When the OpenSwoole server is running, you can use the `OpenSwoole\Http\Client` or any HTTP client to hit routes:

```php
it('returns a 200', function () {
    $response = file_get_contents('http://127.0.0.1:9501/hello');

    expect($response)->toContain('Hello');
});
```

For more robust HTTP testing, create a test helper that boots a server on a random port and uses an HTTP client with a timeout.

## Queue testing

Use the `sync` queue driver to run jobs inline during tests:

```php
it('dispatches a job', function () {
    app()->container->make(\TondbadSwoole\Core\Config::class)
        ->set('queue.default', 'sync');

    (new SendWelcomeEmail('ava@example.com'))->dispatch();

    expect(SendWelcomeEmail::$handled)->toBeTrue();
});
```

## Cache testing

The `memory` cache driver is used by default and is isolated per process:

```php
it('caches a value', function () {
    cache()->set('key', 'value', 60);

    expect(cache()->get('key'))->toBe('value');
});
```

## Pest helpers

The framework exposes Pest helpers for common operations:

```php
$app = app();
$config = config('database.default');
$em = em();
$queue = queue();
$events = event('test.event', $payload);
```

## Architecture tests

The `tests/Architecture` suite uses Pest 5 `arch()` expectations to enforce project-wide rules such as:

- `TondbadSwoole\Contracts` does not depend on concrete framework modules.
- Every source file uses strict types.
- The database `Grammar` delegates dialect work to `DatabaseOperations` and `DatabaseFeatures`.
- Database engines implement `DatabaseEngine` and `DatabaseWrapper` implements `ConnectionInterface`.

## Integration tests

Integration tests live in `tests/Integration`. The database integration suite uses `testcontainers/testcontainers` to spin up real MySQL and PostgreSQL containers and verifies the framework can connect and run queries through `DatabaseManager`.

Run integration tests locally when Docker is available:

```bash
RUN_INTEGRATION_TESTS=1 composer test
```

The tests skip automatically when `RUN_INTEGRATION_TESTS` is not `1` or when the required PDO extension (`pdo_mysql` / `pdo_pgsql`) is missing, so the default suite stays fast and dependency-free.

## CI

The repository includes a GitHub Actions workflow that runs:

```bash
php -l <changed files>
php composer.phar validate --strict
composer test
```

Keep the test suite green before merging.
