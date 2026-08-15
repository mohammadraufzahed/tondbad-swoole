<?php

declare(strict_types=1);

use TondbadSwoole\Console\Commands\MakeControllerCommand;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Output\ConsoleOutput;

function runMakeControllerCommand(MakeControllerCommand $command, array $args): int
{
    return $command->run(
        new ArgvInput($args, $command->getDefinition()),
        new ConsoleOutput(),
    );
}

it('creates a controller using the new attribute style', function () {
    $basePath = $this->tempDir('tondbad_make_controller_test');

    $command = new MakeControllerCommand($basePath);
    $exit = runMakeControllerCommand($command, ['Test']);

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
    runMakeControllerCommand($command, ['Test']);

    expect(runMakeControllerCommand($command, ['Test']))->toBe(1);
});
