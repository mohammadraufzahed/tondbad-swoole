<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
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

    schema()->create('queue_pauses', function (Blueprint $table) {
        $table->string('queue', 255);
        $table->boolean('paused')->default(false);
        $table->integer('created_at', false, true);
        $table->integer('updated_at', false, true);

        $table->unique('queue');
    });

    schema()->create('rate_limits', function (Blueprint $table) {
        $table->string('key', 255);
        $table->integer('count', false, true)->default(0);
        $table->integer('reset_at', false, true);

        $table->unique('key');
    });
});

afterEach(function () {
    schema()->dropIfExists('queue_pauses');
    schema()->dropIfExists('rate_limits');
    schema()->dropIfExists('failed_jobs');
    schema()->dropIfExists('jobs');

    TestQueueJob::$ran = false;
    TestFailingJob::$handleCount = 0;
    TestParentFlowJob::$ran = false;
    TestParentFlowJob::$childValues = [];

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('processes a job synchronously with the sync queue', function () {
    $job = new TestQueueJob();

    $job->dispatch();

    expect(TestQueueJob::$ran)->toBeTrue();
});

it('adds and processes a job from the database queue', function () {
    $queue = queue('database');

    expect($queue)->toBeInstanceOf(DatabaseQueue::class);

    $job = new TestQueueJob();
    $queue->add($job);

    expect($queue->size())->toBe(1);

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 1, 0);

    expect(TestQueueJob::$ran)->toBeTrue();
    expect($queue->size())->toBe(0);
});

it('retries failed jobs up to the configured number of tries', function () {
    $queue = queue('database');
    $job = new TestFailingJob(tries: 3);
    $queue->add($job);

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

it('delays jobs and does not process them before the available time', function () {
    $queue = queue('database');
    $queue->add(new TestQueueJob(), options: ['delay' => 3600]);

    expect($queue->size())->toBe(1);

    $job = $queue->pop('default');

    expect($job)->toBeNull();
});

it('processes higher priority jobs first', function () {
    $queue = queue('database');

    $first = new TestQueueJob();
    $second = new TestQueueJob();

    $queue->add($first, options: ['priority' => 10, 'jobId' => 'low']);
    $queue->add($second, options: ['priority' => 1, 'jobId' => 'high']);

    $popped = $queue->pop('default');

    expect($popped)->not->toBeNull();
    expect($popped->getCustomJobId())->toBe('high');
});

it('applies exponential backoff to failed jobs', function () {
    $queue = queue('database');
    $job = new TestFailingJob(tries: 3)->backoff(['type' => 'exponential', 'delay' => 10]);
    $queue->add($job);

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 3, 0);

    $row = db()->table('jobs')->first();
    expect($row['attempts'])->toBe(1);
    expect($row['available_at'])->toBeGreaterThan(time());
});

it('deduplicates jobs by custom job id', function () {
    $queue = queue('database');
    $job = new TestQueueJob();

    $first = $queue->add($job, options: ['jobId' => 'unique']);
    $second = $queue->add(new TestQueueJob(), options: ['jobId' => 'unique']);

    expect($first)->toBe($second);
    expect($queue->size())->toBe(1);
});

it('returns metrics for the queue', function () {
    $queue = queue('database');
    $queue->add(new TestQueueJob());

    $metrics = $queue->getMetrics('default');

    expect($metrics['waiting'])->toBe(1);
    expect($metrics['active'])->toBe(0);
    expect($metrics['delayed'])->toBe(0);
});

it('drains all jobs from the queue', function () {
    $queue = queue('database');
    $queue->add(new TestQueueJob());
    $queue->add(new TestQueueJob());

    expect($queue->drain('default'))->toBe(2);
    expect($queue->size())->toBe(0);
});

it('gets a job by id', function () {
    $queue = queue('database');
    $id = $queue->add(new TestQueueJob());

    $job = $queue->getJob((int) $id);

    expect($job)->toBeInstanceOf(TestQueueJob::class);
    expect($job->getJobId())->toBe((int) $id);
});

it('supports bulk add', function () {
    $queue = queue('database');

    $ids = $queue->addBulk([
        new TestQueueJob(),
        new TestQueueJob(),
    ]);

    expect($ids)->toHaveCount(2);
    expect($queue->size())->toBe(2);
});

it('pauses and resumes a queue', function () {
    $queue = queue('database');
    $queue->add(new TestQueueJob());

    $queue->pause('default');

    expect($queue->pop('default'))->toBeNull();

    $queue->resume('default');

    $job = $queue->pop('default');
    expect($job)->not->toBeNull();
});

it('emits queue lifecycle events', function () {
    $queue = queue('database');
    $added = $completed = $failed = null;

    $queue->on('added', function (array $data) use (&$added) {
        $added = $data;
    });

    $queue->on('completed', function (array $data) use (&$completed) {
        $completed = $data;
    });

    $queue->on('failed', function (array $data) use (&$failed) {
        $failed = $data;
    });

    $job = new TestQueueJob();
    $queue->add($job);

    expect($added['job'])->toBeInstanceOf(TestQueueJob::class);
    expect($added['id'])->toBeInt();

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 1, 0);

    expect($completed['job'])->toBeInstanceOf(TestQueueJob::class);

    $queue->add(new TestFailingJob(tries: 1));
    $worker->runNextJob($queue, 'default', 1, 0);

    expect($failed['job'])->toBeInstanceOf(TestFailingJob::class);
    expect($failed['exception'])->toBeInstanceOf(\Exception::class);
});

it('persists job progress during handle', function () {
    $queue = queue('database');
    $progressEvent = null;

    $queue->on('progress', function (array $data) use (&$progressEvent) {
        $progressEvent = $data;
    });

    $queue->add((new TestProgressJob())->removeOnComplete(false));

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 1, 0);

    $row = db()->table('jobs')->first();

    expect($row['progress'])->toBe(50);
    expect($progressEvent['progress'])->toBe(50);
    expect($row['status'])->toBe('completed');
});

it('retries a failed job via the retry command', function () {
    $queue = queue('database');
    $queue->add(new TestFailingJob(tries: 1));

    $worker = app()->container->make(Worker::class);
    $worker->runNextJob($queue, 'default', 1, 0);

    expect(db()->table('failed_jobs')->count())->toBe(1);

    $failed = db()->table('failed_jobs')->first();
    $failedId = (int) $failed['id'];

    $command = new \TondbadSwoole\Console\Commands\QueueRetryCommand($this->app->basePath());
    $exit = $command->run(
        new \TondbadSwoole\Console\Input\ArgvInput([$failedId, '--connection=database'], $command->getDefinition()),
        new \TondbadSwoole\Console\Output\ConsoleOutput(),
    );

    expect($exit)->toBe(0);
    expect(db()->table('failed_jobs')->count())->toBe(0);
    expect($queue->size())->toBe(1);
});

it('runs a flow job after all children complete and passes child results to the parent', function () {
    $queue = queue('database');
    $producer = new \TondbadSwoole\Queue\FlowProducer(app()->container->make(\TondbadSwoole\Queue\QueueManager::class));

    $flow = \TondbadSwoole\Queue\Flow\Flow::create(
        new TestParentFlowJob(),
        [
            \TondbadSwoole\Queue\Flow\FlowChild::create(new TestChildFlowJob('child1')),
            \TondbadSwoole\Queue\Flow\FlowChild::create(new TestChildFlowJob('child2')),
        ]
    );

    $producer->add($flow, 'database', 'default');

    $worker = app()->container->make(\TondbadSwoole\Queue\Worker::class);
    while ($worker->runNextJob($queue, 'default', 1, 0)) {
        // process until empty
    }

    expect(TestParentFlowJob::$ran)->toBeTrue();
    expect(TestParentFlowJob::$childValues)->toHaveCount(2);
    expect($queue->size())->toBe(0);
});

it('marks a flow parent as failed when any child fails', function () {
    $queue = queue('database');
    $producer = new \TondbadSwoole\Queue\FlowProducer(app()->container->make(\TondbadSwoole\Queue\QueueManager::class));

    $flow = \TondbadSwoole\Queue\Flow\Flow::create(
        new TestParentFlowJob(),
        [
            \TondbadSwoole\Queue\Flow\FlowChild::create(new TestChildFlowJob('child1')),
            \TondbadSwoole\Queue\Flow\FlowChild::create(new TestFailingFlowJob()),
        ]
    );

    $producer->add($flow, 'database', 'default');

    $worker = app()->container->make(\TondbadSwoole\Queue\Worker::class);
    while ($worker->runNextJob($queue, 'default', 1, 0)) {
        // process until empty
    }

    expect(TestParentFlowJob::$ran)->toBeFalse();

    $metrics = $queue->getMetrics('default');
    expect($metrics['failed'])->toBe(3);
});

it('rate limits job execution based on queue key', function () {
    $queue = queue('database');
    $worker = app()->container->make(\TondbadSwoole\Queue\Worker::class);

    app()->container->bind(
        \TondbadSwoole\Queue\RateLimiter\RateLimiterInterface::class,
        fn () => new \TondbadSwoole\Queue\RateLimiter\DatabaseRateLimiter(db()->connection())
    );

    $queue->add(new TestQueueJob(), 'default');
    $queue->add(new TestQueueJob(), 'default');

    $options = new \TondbadSwoole\Queue\WorkerOptions(
        maxTries: 1,
        sleep: 0,
        rateLimiter: ['max' => 1, 'window' => 60, 'key' => 'queue'],
    );

    $worker->runNextJob($queue, 'default', 1, 0, $options);

    expect(TestQueueJob::$ran)->toBeTrue();
    expect($queue->size())->toBe(1);

    $before = time();

    $worker->runNextJob($queue, 'default', 1, 0, $options);

    $row = db()->table('jobs')
        ->where('status', 'delayed')
        ->first();

    expect($row)->not->toBeNull();
    expect($row['available_at'])->toBeGreaterThan($before);
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

class TestProgressJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public function handle(): void
    {
        $this->progress(50);
    }
}

class TestChildFlowJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public function __construct(public readonly string $name) {}

    public function handle(): void
    {
        $this->setResult(['name' => $this->name]);
    }
}

class TestFailingFlowJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public function handle(): void
    {
        throw new Exception('Child failed');
    }
}

class TestParentFlowJob extends \TondbadSwoole\Queue\Jobs\Job
{
    public static bool $ran = false;

    /**
     * @var array<string, array<string, mixed>>
     */
    public static array $childValues = [];

    public function handle(): void
    {
        self::$ran = true;
        self::$childValues = $this->getChildrenValues();
    }
}
