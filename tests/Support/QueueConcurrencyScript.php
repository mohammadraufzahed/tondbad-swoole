<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Drivers\RedisQueue;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Worker;

class ConcurrencyJob extends Job
{
    public function __construct(public readonly int $index) {}

    public function handle(): void {}
}

$client = new Predis\Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 11,
]);

$client->flushdb();

$queue = new RedisQueue($client, 'tondbad', 'default', 60, 0);

for ($i = 0; $i < 10; $i++) {
    $queue->add(new ConcurrencyJob($i));
}

$processed = [];

try {
    OpenSwoole\Runtime::enableCoroutine(OpenSwoole\Runtime::HOOK_TCP | OpenSwoole\Runtime::HOOK_SLEEP);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit(1);
}

$done = new OpenSwoole\Coroutine\Channel(10);

OpenSwoole\Coroutine::run(function () use ($done, $queue): void {
    $worker = new Worker(new Container());

    for ($i = 0; $i < 4; $i++) {
        OpenSwoole\Coroutine::create(function () use ($worker, $done): void {
            $queue = new RedisQueue(new Predis\Client([
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 11,
            ]), 'tondbad', 'default', 60, 0);

            while (($job = $queue->pop()) !== null) {
                $worker->process($job, $queue, null, null);
                $done->push($job->getJobId());
            }

            $done->push(null);
        });
    }

    $processed = [];
    $sentinels = 0;

    while (count($processed) < 10 || $sentinels < 4) {
        $id = $done->pop();

        if ($id === null) {
            $sentinels++;
            continue;
        }

        $processed[] = $id;
    }

    $done->close();

    echo json_encode($processed);
});
