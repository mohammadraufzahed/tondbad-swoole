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

## Connection pooling

Under OpenSwoole, `DatabaseManager` uses a `SwoolePdoPool`. Outside Swoole it falls back to `SimplePdoPool` for local development and testing. Each coroutine (or request) gets its own connection from the pool and it is returned when the context is cleared.
