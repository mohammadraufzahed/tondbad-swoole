<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Drivers\RedisQueue;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Worker;
use TondbadSwoole\Queue\WorkerOptions;

function redisClient(): Predis\Client
{
    return new Predis\Client([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 10,
    ]);
}

function redisAvailable(): bool
{
    static $available = null;

    if ($available === null) {
        try {
            redisClient()->ping();
            $available = true;
        } catch (Throwable) {
            $available = false;
        }
    }

    return $available;
}

function flushRedis(): void
{
    redisClient()->flushdb();
}

beforeEach(function () {
    if (!redisAvailable()) {
        return;
    }

    flushRedis();
});

it('adds and pops a job from the redis queue', function () {
    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);
    $queue->add(new RedisTestJob());

    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(RedisTestJob::class);
    expect($queue->pop())->toBeNull();
})->skip(fn () => !redisAvailable(), 'Redis not available');

it('processes a job through the worker', function () {
    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);
    $worker = new Worker(new Container());
    RedisTestJob::$ran = false;

    $queue->add(new RedisTestJob());
    $job = $queue->pop();

    $worker->process($job, $queue, null, null);

    expect(RedisTestJob::$ran)->toBeTrue();
    expect($queue->getMetrics()['completed'])->toBe(1);
})->skip(fn () => !redisAvailable(), 'Redis not available');

it('retries failed jobs with backoff', function () {
    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);
    $worker = new Worker(new Container());

    $queue->add((new RedisFailingJob())->tries(1));
    $job = $queue->pop();
    $worker->process($job, $queue, null, null);

    expect($queue->getMetrics()['failed'])->toBe(1);
})->skip(fn () => !redisAvailable(), 'Redis not available');

it('delays jobs and does not process them before the available time', function () {
    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);

    $queue->add((new RedisTestJob())->delay(2));

    expect($queue->pop())->toBeNull();
    sleep(3);

    $popped = $queue->pop();

    expect($popped)->toBeInstanceOf(RedisTestJob::class);
})->skip(fn () => !redisAvailable(), 'Redis not available');

it('does not allow multiple workers to pop the same job', function () {
    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);
    $queue->add(new RedisTestJob());

    $first = $queue->pop();
    $second = $queue->pop();

    expect($first)->toBeInstanceOf(RedisTestJob::class);
    expect($second)->toBeNull();
})->skip(fn () => !redisAvailable(), 'Redis not available');

it('processes a flow job after children complete', function () {
    $config = new \TondbadSwoole\Core\Config(new \TondbadSwoole\Core\Env());
    $config->set('queue.connections.redis', [
        'driver' => 'redis',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 10,
        'prefix' => 'tondbad',
        'queue' => 'default',
        'retry_after' => 60,
        'block_for' => 0,
    ]);

    $queue = new RedisQueue(redisClient(), 'tondbad', 'default', 60, 0);
    $worker = new Worker(new Container());
    $producer = new \TondbadSwoole\Queue\FlowProducer(new \TondbadSwoole\Queue\QueueManager(
        $config,
        new Container(),
        new \TondbadSwoole\Database\DatabaseManager($config),
    ));

    RedisParentFlowJob::$ran = false;
    RedisParentFlowJob::$childValues = [];

    $flow = \TondbadSwoole\Queue\Flow\Flow::create(
        new RedisParentFlowJob(),
        [
            \TondbadSwoole\Queue\Flow\FlowChild::create(new RedisChildFlowJob('child1')),
            \TondbadSwoole\Queue\Flow\FlowChild::create(new RedisChildFlowJob('child2')),
        ]
    );

    $producer->add($flow, 'redis', 'default');

    while (($job = $queue->pop()) !== null) {
        $worker->process($job, $queue, null, null);
    }

    expect(RedisParentFlowJob::$ran)->toBeTrue();
    expect(RedisParentFlowJob::$childValues)->toHaveCount(2);
})->skip(fn () => !redisAvailable(), 'Redis not available');

class RedisTestJob extends Job
{
    public static bool $ran = false;

    public function handle(): void
    {
        self::$ran = true;
    }
}

class RedisFailingJob extends Job
{
    public function handle(): void
    {
        throw new Exception('Failed on purpose');
    }
}

class RedisChildFlowJob extends Job
{
    public function __construct(public readonly string $name) {}

    public function handle(): void
    {
        $this->setResult(['name' => $this->name]);
    }
}

class RedisParentFlowJob extends Job
{
    public static bool $ran = false;

    public static array $childValues = [];

    public function handle(): void
    {
        self::$ran = true;
        self::$childValues = $this->getChildrenValues();
    }
}
