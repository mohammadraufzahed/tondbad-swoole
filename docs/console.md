# Console

Tondbād ships with a `tondbad` CLI using a lightweight console application.

## Running commands

```bash
php bin/tondbad
php bin/tondbad serve
php bin/tondbad migrate
```

Running `php bin/tondbad` without a command prints the available command list.

When called as `php bin/tondbad <command>`, the framework boots in `console` mode: providers register but the HTTP/gRPC server is not started.

## Built-in commands

| Command | Description |
|---|---|
| `serve` | Start the OpenSwoole HTTP server |
| `serve:grpc` | Start the OpenSwoole gRPC server |
| `route:list` | List all registered routes |
| `route:cache` | Compile routes to a cache file |
| `cache:clear` | Clear compiled route and framework caches |
| `--version` / `-V` | Print the framework version |
| `migrate` | Run pending migrations |
| `migrate:fresh` | Drop all tables and run migrations |
| `migrate:rollback` | Rollback the last batch |
| `migrate:status` | Show migration status |
| `make:migration` | Create a new migration |
| `make:model` | Create a new model |
| `make:controller` | Create a new controller |
| `make:middleware` | Create a new middleware |
| `make:provider` | Create a new service provider |
| `make:job` | Create a new job |
| `make:event` | Create a new event class |
| `make:listener` | Create a new event listener |
| `make:request` | Create a new form request |
| `make:guard` | Create a new guard factory |
| `make:policy` | Create a new policy |
| `queue:work` | Process queue jobs |
| `schedule:work` | Run the scheduler process |
| `schedule:list` | List scheduled events |
| `hash:make` | Hash a string |
| `hash:check` | Check a hash |

## Creating a custom command

Create a class in `app/Console/Commands/` implementing `TondbadSwoole\Console\CommandInterface` or extending `TondbadSwoole\Console\Commands\Command`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use TondbadSwoole\Console\Commands\Command;

class ReportDailyCommand extends Command
{
    public function getName(): string
    {
        return 'report:daily';
    }

    public function getDescription(): string
    {
        return 'Generate the daily report.';
    }

    public function run(array $args): int
    {
        fwrite(STDOUT, "Generating report...\n");

        // ...

        return 0;
    }
}
```

The command is auto-discovered from `app/Console/Commands` at boot.

## Parsing command arguments

The `$args` array contains everything after the command name. Parse it manually or use `getopt`:

```php
public function run(array $args): int
{
    $opts = getopt('h:p:', ['host:', 'port:'], $restIndex);

    $host = $opts['host'] ?? $opts['h'] ?? '127.0.0.1';
    $port = (int) ($opts['port'] ?? $opts['p'] ?? '9501');

    return 0;
}
```

## Schedule commands

Commands can be scheduled in `routes/console.php`:

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Console\Schedule;

return function (Schedule $schedule): void {
    $schedule->command('report:daily')->daily();
};
```

## Adding command paths

`ConsoleServiceProvider` discovers commands from:

- `vendor/mohammadraufzahed/tondbad-swoole/src/Console/Commands`
- `app/Console/Commands`

You can configure the namespace and path in `config/app.php`:

```php
'paths' => [
    'commands' => 'app/Console/Commands',
],

'namespaces' => [
    'commands' => 'App\\Console\\Commands\\',
],
```
