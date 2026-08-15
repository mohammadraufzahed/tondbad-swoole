<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Console;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Authorize;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Commands\Command;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\ConsoleOutput;
use TondbadSwoole\Console\Output\OutputInterface;

function makeApplication(?App $app = null): Application
{
    $app ??= new App(__DIR__ . '/../../../..');

    return $app->container->make(Application::class);
}

function runApplication(Application $console, array $argv, ?bool $ansi = false): array
{
    $stream = fopen('php://memory', 'w+');
    $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, $ansi, $stream);
    $exitCode = $console->run($argv, $output);
    rewind($stream);
    $captured = stream_get_contents($stream);
    fclose($stream);

    return [$exitCode, $captured];
}

#[AsCommand('test:noop', 'No-op command for CLI tests.')]
class TestNoopCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }
}

#[AsCommand('test:color', 'Command that writes colored output.')]
class TestColorCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->success('colored');

        return 0;
    }
}

#[AsCommand('test:email', 'Command that validates an email option.')]
class TestEmailCommand extends Command
{
    #[Option('email', mode: \TondbadSwoole\Console\Input\InputOption::VALUE_OPTIONAL, schema: 'email')]
    public ?string $email = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->email ?? '');

        return 0;
    }
}

#[AsCommand('test:auth', 'Command that requires admin ability.')]
#[Authorize('admin')]
class TestAuthorizedCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('authorized');

        return 0;
    }
}

it('shows global help and groups commands by namespace', function () {
    $console = makeApplication();
    $console->register(new TestNoopCommand($console->getBasePath()));

    [$exit, $output] = runApplication($console, ['tondbad']);

    expect($exit)->toBe(1);
    expect($output)->toContain('Available commands:');
    expect($output)->toContain('make:');
    expect($output)->toContain('cache:');
    expect($output)->toContain('test:noop');
});

it('shows per-command help', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', 'hash:make', '--help']);

    expect($exit)->toBe(0);
    expect($output)->toContain('Usage:');
    expect($output)->toContain('value');
});

it('fails on missing required argument', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', 'hash:make']);

    expect($exit)->toBe(1);
    expect($output)->toContain('Missing required argument');
});

it('fails on unknown option', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', 'hash:make', 'secret', '--unknown']);

    expect($exit)->toBe(1);
    expect($output)->toContain('Unknown option');
});

it('honors quiet mode', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', '-q', 'cache:status']);

    expect($exit)->toBe(0);
    expect($output)->toBe('');
});

it('accepts verbose flags without error', function () {
    $console = makeApplication();
    $console->register(new TestNoopCommand($console->getBasePath()));

    [$exit, $output] = runApplication($console, ['tondbad', '-vvv', 'test:noop']);

    expect($exit)->toBe(0);
});

it('disables ansi output with --no-ansi', function () {
    $console = makeApplication();
    $console->register(new TestColorCommand($console->getBasePath()));

    [, $withAnsi] = runApplication($console, ['tondbad', 'test:color'], ansi: null);
    [, $withoutAnsi] = runApplication($console, ['tondbad', '--no-ansi', 'test:color'], ansi: null);

    expect($withAnsi)->toContain("\033[");
    expect($withoutAnsi)->not->toContain("\033[");
});

it('validates option values using the schema engine', function () {
    $console = makeApplication();
    $console->register(new TestEmailCommand($console->getBasePath()));

    [$invalid, $invalidOutput] = runApplication($console, ['tondbad', 'test:email', '--email=foo']);
    [$valid, $validOutput] = runApplication($console, ['tondbad', 'test:email', '--email=foo@bar.com']);

    expect($invalid)->toBe(1);
    expect($invalidOutput)->toContain('Invalid value');

    expect($valid)->toBe(0);
    expect($validOutput)->toContain('foo@bar.com');
});

it('denies unauthorized command execution via gate', function () {
    $app = new App(__DIR__ . '/../../../..');
    $console = makeApplication($app);
    $console->register(new TestAuthorizedCommand($console->getBasePath()));

    [$exit, $output] = runApplication($console, ['tondbad', 'test:auth']);

    expect($exit)->toBe(1);
    expect($output)->toContain('not authorized');
});

it('generates bash completion script', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', 'completion', 'bash']);

    expect($exit)->toBe(0);
    expect($output)->toContain('_tondbad_completion');
    expect($output)->toContain('hash:make');
});

it('lists routes with styled output', function () {
    $console = makeApplication();

    [$exit, $output] = runApplication($console, ['tondbad', 'route:list']);

    expect($exit)->toBe(0);
    expect($output)->toContain('No routes registered');
});
