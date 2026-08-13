# Database

Tondbād's database layer is coroutine-safe and built around a connection pool and a query grammar per driver.

## Configuration

`config/database.php`:

```php
<?php

declare(strict_types=1);

return [
    'default' => $env->get('db.connection', 'mysql'),

    'migrations' => $env->get('db.migrations', 'database/migrations'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $env->get('db.mysql.host', '127.0.0.1'),
            'port' => (int) $env->get('db.mysql.port', 3306),
            'database' => $env->get('db.mysql.database', 'tondbad'),
            'username' => $env->get('db.mysql.username', 'root'),
            'password' => $env->get('db.mysql.password', ''),
            'charset' => $env->get('db.mysql.charset', 'utf8mb4'),
            'prefix' => $env->get('db.mysql.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => (int) $env->get('db.mysql.pool.min', 1),
                'max' => (int) $env->get('db.mysql.pool.max', 10),
                'wait_timeout' => (float) $env->get('db.mysql.pool.wait_timeout', 3.0),
                'max_age' => (float) $env->get('db.mysql.pool.max_age', 600.0),
                'max_usage' => (int) $env->get('db.mysql.pool.max_usage', 0),
                'health_check' => (bool) $env->get('db.mysql.pool.health_check', true),
                'check_interval' => (float) $env->get('db.mysql.pool.check_interval', 30.0),
            ],
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $env->get('db.sqlite.database', ':memory:'),
            'prefix' => $env->get('db.sqlite.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => 1,
                'max' => 1,
                'wait_timeout' => 3.0,
                'max_age' => (float) $env->get('db.sqlite.pool.max_age', 0.0),
                'max_usage' => (int) $env->get('db.sqlite.pool.max_usage', 0),
                'health_check' => (bool) $env->get('db.sqlite.pool.health_check', false),
                'check_interval' => (float) $env->get('db.sqlite.pool.check_interval', 30.0),
            ],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $env->get('db.pgsql.host', '127.0.0.1'),
            'port' => (int) $env->get('db.pgsql.port', 5432),
            'database' => $env->get('db.pgsql.database', 'tondbad'),
            'username' => $env->get('db.pgsql.username', 'postgres'),
            'password' => $env->get('db.pgsql.password', ''),
            'charset' => $env->get('db.pgsql.charset', 'utf8'),
            'prefix' => $env->get('db.pgsql.prefix', ''),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'pool' => [
                'min' => (int) $env->get('db.pgsql.pool.min', 1),
                'max' => (int) $env->get('db.pgsql.pool.max', 10),
                'wait_timeout' => (float) $env->get('db.pgsql.pool.wait_timeout', 3.0),
                'max_age' => (float) $env->get('db.pgsql.pool.max_age', 600.0),
                'max_usage' => (int) $env->get('db.pgsql.pool.max_usage', 0),
                'health_check' => (bool) $env->get('db.pgsql.pool.health_check', true),
                'check_interval' => (float) $env->get('db.pgsql.pool.check_interval', 30.0),
            ],
        ],
    ],
];
```

## Connections

```php
$manager = db(); // DatabaseManager

$users = $manager->table('users')->where('active', true)->get();

// Or get a specific connection
$connection = db('mysql');
$rows = $connection->select('select * from users where active = ?', [1]);
```

## Raw queries

```php
$rows = db()->select('select * from users where id > ?', [10]);

$affected = db()->statement('update users set active = 1');

$count = db()->select('select count(*) as total from users')[0]['total'] ?? 0;
```

## Query builder

```php
$users = db()->table('users')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

The fluent query builder is the same engine used by the ORM `ModelBuilder`.

## Transactions

```php
db()->transaction(function ($connection) {
    $connection->table('accounts')->where('id', 1)->update(['balance' => db()->raw('balance - 100')]);
    $connection->table('accounts')->where('id', 2)->update(['balance' => db()->raw('balance + 100')]);
});
```

## Row-level locking

The query builder supports pessimistic locking where the driver allows it:

```php
$user = db()->table('users')
    ->where('id', 1)
    ->lockForUpdate()
    ->skipLocked()
    ->first();
```

`lockForUpdate()` adds `FOR UPDATE`. `skipLocked()` adds `SKIP LOCKED` on drivers that support it (PostgreSQL, MySQL 8+). SQLite falls back to no locking.

## Schema builder

```php
schema()->create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email', 191)->unique();
    $table->text('bio')->nullable();
    $table->json('settings')->default('{}');
    $table->boolean('active')->default(true);
    $table->timestamps();
});

schema()->table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->index('email');
});

schema()->dropIfExists('users');
```

Supported column types include `id`, `bigIncrements`, `string`, `text`, `integer`, `bigInteger`, `boolean`, `json`, `datetime`, `timestamp`, `date`, `time`, `enum`, `decimal`, `float`, `double`, `binary`, `uuid`, and `char`.

## Database engines

Each connection is backed by a driver-specific `DatabaseEngine` (`MySqlEngine`, `PostgresEngine`, `SqliteEngine`). The engine provides:

- `DatabaseOperations` — SQL dialect details such as identifier quoting, limit/offset syntax, default formatting, and DDL generation.
- `DatabaseFeatures` — capability flags for transactions, savepoints, `RETURNING`, JSON fields, deferrable constraints, unsigned modifiers, autoincrement, etc.
- `Grammar` — assembles `SELECT`/`INSERT`/`UPDATE`/`DELETE` and `Schema` statements while delegating driver-specific fragments to `DatabaseOperations`.

Register a custom engine from a service provider or bootstrap file:

```php
use TondbadSwoole\Database\DatabaseManager;

$manager = app(DatabaseManager::class);
$manager->extend('mariadb', \App\Database\Engines\MariaDbEngine::class);
```

## Connection pooling

`DatabaseManager` creates a `LazyPool` that chooses the right implementation for the runtime:

- MySQL uses `SwoolePdoPool` under OpenSwoole (PDO + `mysqlnd` + `SWOOLE_HOOK_TCP`) and `SimplePdoPool` outside coroutines.
- PostgreSQL uses `PostgresContextPool`, which creates a per-checkout `SwoolePostgresPdo` backed by `OpenSwoole\Coroutine\PostgreSQL` inside a coroutine, and falls back to a regular PDO connection outside a coroutine. This avoids sharing the native Postgres client across coroutines, which is unsafe.
- SQLite always uses `SimplePdoPool` and should not be used for concurrent OpenSwoole workloads.

In all cases each coroutine (or request) gets its own connection and it is returned when the lifecycle ends.

Pool options:

- `min` — minimum number of idle connections.
- `max` — maximum number of connections.
- `wait_timeout` — seconds to wait for an available connection.
- `max_age` — seconds a connection may live before it is discarded (`0` = unlimited).
- `max_usage` — number of times a connection may be borrowed before it is retired (`0` = unlimited).
- `health_check` — run a cheap `SELECT 1` before reusing an idle connection.
- `check_interval` — minimum seconds between health checks on the same connection.

Request cleanup is handled by `RouteDispatcher::dispatch()` which calls `DatabaseManager::closeOldConnections()` in a `finally` block before clearing the request context.
