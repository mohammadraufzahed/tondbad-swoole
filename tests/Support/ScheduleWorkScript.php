<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Commands\ScheduleWorkCommand;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Output\ConsoleOutput;

$marker = sys_get_temp_dir() . '/tondbad_schedule_work_' . uniqid() . '.marker';

$app = new App(__DIR__ . '/../..');

$schedule = $app->container->make(\TondbadSwoole\Scheduling\Schedule::class);
$schedule->call(function () use ($marker): void {
    file_put_contents($marker, 'ran');
})->everyMinute();

$command = new ScheduleWorkCommand($app->basePath());
$exitCode = $command->run(
    new ArgvInput(['--run-once'], $command->getDefinition()),
    new ConsoleOutput(),
);

echo json_encode([
    'exitCode' => $exitCode,
    'marker' => $marker,
    'ran' => file_exists($marker) && file_get_contents($marker) === 'ran',
]);

if (file_exists($marker)) {
    unlink($marker);
}
