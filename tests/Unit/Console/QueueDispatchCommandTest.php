<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Console;

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Output\ConsoleOutput;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Queue\Drivers\DatabaseQueue;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue', 255);
        $table->text('payload');
        $table->integer('attempts', false, true);
        $table->integer('reserved_at')->nullable();
        $table->integer('available_at', false, true);
        $table->integer('created_at', false, true);
        $table->integer('priority', false, true)->default(0);
        $table->integer('delay', false, true)->nullable();
        $table->string('backoff_type', 20)->nullable();
        $table->integer('backoff_value', false, true)->nullable();
        $table->integer('timeout', false, true)->nullable();
        $table->integer('progress', false, true)->default(0);
        $table->string('deduplication_id', 255)->nullable();
        $table->integer('parent_id', false, true)->nullable();
        $table->integer('children_count', false, true)->default(0);
        $table->integer('completed_children_count', false, true)->default(0);
        $table->text('result')->nullable();
        $table->string('status', 20)->default('waiting');
    });

    schema()->create('failed_jobs', function (Blueprint $table) {
        $table->id();
        $table->string('connection', 255);
        $table->string('queue', 255)->nullable();
        $table->text('payload');
        $table->text('exception');
        $table->integer('failed_at', false, true);
    });
});

it('dispatches a job onto the database queue', function () {
    /** @var Application $console */
    $console = $this->app->container->make(Application::class);

    $stream = fopen('php://memory', 'w+');
    $output = new ConsoleOutput(ConsoleOutput::VERBOSITY_NORMAL, false, $stream);

    $command = new \TondbadSwoole\Console\Commands\QueueDispatchCommand($this->app->basePath());
    $exitCode = $command->run(
        new ArgvInput([
            'TondbadSwoole\\Tests\\Support\\QueueWorkConcurrencyJob',
            '--connection=database',
            '--data={"0":42}',
        ], $command->getDefinition()),
        $output,
    );

    expect($exitCode)->toBe(0);

    /** @var DatabaseQueue $queue */
    $queue = $this->app->container->make(\TondbadSwoole\Queue\QueueManager::class)->connection('database');

    expect($queue->size())->toBe(1);
});
