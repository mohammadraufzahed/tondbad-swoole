<?php

declare(strict_types=1);

use TondbadSwoole\Console\Commands\MakeControllerCommand;

it('creates a controller using the new attribute style', function () {
    $basePath = $this->tempDir('tondbad_make_controller_test');

    $command = new MakeControllerCommand($basePath);
    $exit = $command->run(['Test']);

    expect($exit)->toBe(0);

    $file = $basePath . '/app/Http/Controllers/TestController.php';
    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);
    expect($content)->toContain('class TestController');
    expect($content)->toContain("#[Controller('/test')]");
    expect($content)->toContain('#[Get]');
    expect($content)->not->toContain('#[Endpoint(');
    expect(exec('php -l ' . escapeshellarg($file) . ' 2>&1'))->toContain('No syntax errors');
});

it('refuses to overwrite an existing controller', function () {
    $basePath = $this->tempDir('tondbad_make_controller_test');

    $command = new MakeControllerCommand($basePath);
    $command->run(['Test']);

    expect($command->run(['Test']))->toBe(1);
});
