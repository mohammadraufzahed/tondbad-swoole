<?php

declare(strict_types=1);

use TondbadSwoole\Console\Commands\MakePolicyCommand;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Output\ConsoleOutput;

it('creates a policy stub', function () {
    $basePath = $this->tempDir('tondbad_make_policy_test');

    $command = new MakePolicyCommand($basePath);
    $exit = $command->run(
        new ArgvInput(['Post'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    expect($exit)->toBe(0);

    $file = $basePath . '/app/Policies/PostPolicy.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class PostPolicy implements Policy');
    expect($content)->toContain('use HandlesAuthorization;');
    expect(exec('php -l ' . escapeshellarg($file) . ' 2>&1'))->toContain('No syntax errors');
});

it('refuses to overwrite an existing policy', function () {
    $basePath = $this->tempDir('tondbad_make_policy_test');

    $command = new MakePolicyCommand($basePath);
    $command->run(
        new ArgvInput(['Post'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    $exit = $command->run(
        new ArgvInput(['Post'], $command->getDefinition()),
        new ConsoleOutput(),
    );

    expect($exit)->toBe(1);
});
