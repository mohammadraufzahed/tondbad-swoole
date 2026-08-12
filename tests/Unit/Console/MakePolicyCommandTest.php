<?php

declare(strict_types=1);

use TondbadSwoole\Console\Commands\MakePolicyCommand;

it('creates a policy stub', function () {
    $basePath = $this->tempDir('tondbad_make_policy_test');

    $command = new MakePolicyCommand($basePath);
    $exit = $command->run(['Post']);

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
    $command->run(['Post']);

    expect($command->run(['Post']))->toBe(1);
});
