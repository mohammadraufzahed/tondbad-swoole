<?php

declare(strict_types=1);

use TondbadSwoole\Console\Commands\MakeGuardCommand;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Output\ConsoleOutput;

it('creates a guard factory stub', function () {
    $basePath = $this->tempDir('tondbad_make_guard_test');

    $command = new MakeGuardCommand($basePath);
    $exit = $command->run(
        new ArgvInput(['Jwt'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    expect($exit)->toBe(0);

    $file = $basePath . '/app/Auth/Guards/JwtGuardFactory.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class JwtGuardFactory implements GuardFactory');
    expect($content)->toContain('implements Guard');
    expect(exec('php -l ' . escapeshellarg($file) . ' 2>&1'))->toContain('No syntax errors');
});

it('refuses to overwrite an existing guard', function () {
    $basePath = $this->tempDir('tondbad_make_guard_test');

    $command = new MakeGuardCommand($basePath);
    $command->run(
        new ArgvInput(['Jwt'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    $exit = $command->run(
        new ArgvInput(['Jwt'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    expect($exit)->toBe(1);
});
