# Console

Tondbād ships with a zero-dependency, OpenSwoole-native `tondbad` CLI. It is modeled on Symfony Console (`configure()`/`execute()`), Clap (attribute-driven arguments/options), and Cobra (hierarchical subcommands), but stays fully inside the framework.

## Running commands

```bash
php bin/tondbad
php bin/tondbad serve
php bin/tondbad migrate
php bin/tondbad --help
php bin/tondbad queue:dispatch App.Jobs.SendEmail --data='{"0":1}' -c=database
```

Running `php bin/tondbad` without a command prints the available command list, grouped by namespace (`make:*`, `cache:*`, etc.).

When called as `php bin/tondbad <command>`, the framework boots in `console` mode: providers register but the HTTP/gRPC server is not started.

## Global options

| Option | Description |
|---|---|
| `--help` / `-h` | Show command or global help |
| `--version` / `-V` | Print the framework version |
| `--quiet` / `-q` | Suppress all output |
| `--verbose` / `-v` / `-vv` / `-vvv` | Increase verbosity |
| `--ansi` / `--no-ansi` | Force enable/disable ANSI output |
| `--env` / `-e` | Set `APP_ENV` before the framework boots |

## Built-in commands

| Command | Description |
|---|---|
| `serve` | Start the OpenSwoole HTTP server |
| `serve:grpc` | Start the OpenSwoole gRPC server |
| `route:list` | List all registered routes |
| `route:cache` | Compile routes to a cache file |
| `cache:status` | Show cache statistics |
| `cache:clear` | Clear compiled route and framework caches |
| `cache:forget-tags` | Invalidate cache entries by tag |
| `migrate` | Run pending migrations |
| `migrate:fresh` | Drop all tables and run migrations |
| `migrate:rollback` | Rollback the last batch |
| `migrate:status` | Show migration status |
| `make:command` | Create a new console command |
| `make:migration` | Create a new migration |
| `make:model` | Create a new model |
| `make:controller` | Create a new controller |
| `make:middleware` | Create a new middleware |
| `make:provider` | Create a new service provider |
| `make:job` | Create a new job class |
| `make:event` | Create a new event class |
| `make:listener` | Create a new event listener |
| `make:request` | Create a form request |
| `make:guard` | Create a guard factory |
| `make:policy` | Create a policy |
| `queue:work` | Process queue jobs |
| `queue:dispatch` | Dispatch a job from the command line |
| `queue:status` | Show queue metrics by status |
| `queue:retry` | Retry a failed job by id |
| `queue:retry-failed` | Retry all failed jobs for a queue |
| `schedule:work` | Run the scheduler process |
| `schedule:list` | List scheduled events |
| `schedule:run` | Run due scheduled events once |
| `hash:make` | Hash a string |
| `hash:check` | Check a hash |
| `auth:clear-sessions` | Clear expired/stale sessions |
| `completion` | Generate shell completion script |

## Creating a custom command

Create a class in `app/Console/Commands/` extending `TondbadSwoole\Console\Commands\Command`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

#[AsCommand('report:daily', 'Generate the daily report.')]
class ReportDailyCommand extends Command
{
    #[Argument('type', mode: \TondbadSwoole\Console\Input\InputArgument::OPTIONAL, description: 'Report type', default: 'summary')]
    public string $type = 'summary';

    #[Option('date', shortcut: 'd', mode: \TondbadSwoole\Console\Input\InputOption::VALUE_OPTIONAL, description: 'Report date', default: 'today')]
    public string $date = 'today';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->info("Generating {$this->type} report for {$this->date}");

        return 0;
    }
}
```

Commands are auto-discovered from `app/Console/Commands` or may be listed under `config/app.php` `commands`.

## Arguments and options

- Use `#[Argument]` for positional values.
- Use `#[Option]` for `--name` / `-n` switches.
- Modes follow `InputArgument` / `InputOption` constants (`REQUIRED`, `OPTIONAL`, `IS_ARRAY`, `VALUE_NONE`, `VALUE_OPTIONAL`, `VALUE_REQUIRED`).
- `schema` accepts shortcuts (`int`, `bool`, `float`, `email`, `uuid`, `json`, `string`) or a full `TondbadSwoole\Validation\Schema` object.
- `allowed` restricts a value to an allowed set.

```php
#[Option('role', mode: VALUE_OPTIONAL, allowed: ['admin', 'user'])]
public string $role = 'user';

#[Option('limit', mode: VALUE_OPTIONAL, schema: 'int', default: 10)]
public int $limit = 10;
```

## Authorization

Protect a command with the `#[Authorize]` attribute. The command will be denied when the configured `Gate` does not allow the ability:

```php
use TondbadSwoole\Console\Attributes\Authorize;

#[AsCommand('admin:cleanup', 'Run admin cleanup.')]
#[Authorize('admin')]
class AdminCleanupCommand extends Command
{
    // ...
}
```

## Validation

All attribute arguments/options are validated through the framework `Schema` engine before `execute()` is called. Invalid values produce a clear error and exit code `1`:

```php
#[Option('email', mode: VALUE_OPTIONAL, schema: 'email')]
public ?string $email = null;
```

## Output helpers

`OutputInterface` provides styled blocks and helpers:

```php
$output->success('Done.');
$output->error('Something went wrong.');
$output->warning('Check this.');
$output->info('Working...');
$output->table(['ID', 'Name'], [['1', 'Alice'], ['2', 'Bob']]);
$output->progressBar(100);
$name = $output->ask('What is your name?');
$confirm = $output->confirm('Continue?');
```

## Scheduling

Commands can be scheduled in `routes/schedule.php` (or `routes/console.php` depending on project configuration):

```php
<?php

declare(strict_types=1);

use TondbadSwoole\Scheduling\Schedule;

return function (Schedule $schedule): void {
    $schedule->command('report:daily')->daily();
};
```

## Shell completion

Generate completion for `bash` or `zsh`:

```bash
php bin/tondbad completion bash > /etc/bash_completion.d/tondbad
php bin/tondbad completion zsh
```

## OpenSwoole coroutine support

Short commands automatically run inside `OpenSwoole\Coroutine::run()` with `SWOOLE_HOOK_ALL`. Long-running commands (`serve`, `queue:work`, `schedule:work`, `serve:grpc`) are marked `coroutine: false` and keep their own event loops.

Disable coroutine wrapping for a custom command:

```php
#[AsCommand('import:large', 'Import a large CSV.', coroutine: false)]
class ImportLargeCommand extends Command
{
    // ...
}
```
