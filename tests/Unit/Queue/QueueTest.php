<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Queue\Drivers\DatabaseQueue;
use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\Worker;

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

afterEach(function () {
    schema()->dropIfExists('failed_jobs');
    schema()->dropIfExists('jobs');
});

it('processes a job synchronously with the sync queue', function () {
    $job = new TestQueueJob();

    $job->dispatch();

    expect(TestQueueJob::$ran)->toBeTrue();
});

it('pops and processes a job from the database queue', function () {
    $queue = queue('database');

    expect($queue)->toBeInstanceOf(DatabaseQueue::class);

    $job = new TestQueueJob();
    $queue->push($job);

    expect($queue->size())->toBe(1);

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 1, 0);

    expect(TestQueueJob::$ran)->toBeTrue();
    expect($queue->size())->toBe(0);
});

it('retries failed jobs up to the configured number of tries', function () {
    $queue = queue('database');
    $job = new TestFailingJob(tries: 3);
    $queue->push($job);

    $worker = app()->container->make(Worker::class);
    for ($i = 0; $i < 3; $i++) {
        $worker->runNextJob($queue, 'default', 3, 0);
    }

    expect(TestFailingJob::$handleCount)->toBe(3);
    expect($queue->size())->toBe(0);

    $failed = db()->table('failed_jobs')->get();
    expect($failed)->toHaveCount(1);
    expect($failed[0]['exception'])->toBe('Failed on purpose');
});

it('dispatches a job onto the default queue', function () {
    TestQueueJob::$ran = false;

    (new TestQueueJob())->dispatch();

    expect(TestQueueJob::$ran)->toBeTrue();
});

class TestQueueJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public static bool $ran = false;

    public function handle(): void
    {
        self::$ran = true;
    }
}

class TestFailingJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public static int $handleCount = 0;

    public function __construct(?int $tries = null)
    {
        $this->tries = $tries;
    }

    public function handle(): void
    {
        self::$handleCount++;

        throw new Exception('Failed on purpose');
    }
}
