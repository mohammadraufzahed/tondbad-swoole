<?php

declare(strict_types=1);

use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Queue\Worker;

function queueRedisClient(): Predis\Client
{
    return new Predis\Client([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 11,
    ]);
}

function queueRedisAvailable(): bool
{
    static $available = null;

    if ($available === null) {
        try {
            queueRedisClient()->ping();
            $available = true;
        } catch (Throwable) {
            $available = false;
        }
    }

    return $available;
}

it('processes redis jobs concurrently without duplicate handling', function () {
    $script = __DIR__ . '/../../Support/QueueConcurrencyScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $processed = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($processed)->toBeArray()->toHaveCount(10);
    expect(array_unique($processed))->toHaveCount(10);
})->skip(fn () => !queueRedisAvailable(), 'Redis not available');

class ConcurrencyJob extends Job
{
    public function __construct(public readonly int $index) {}

    public function handle(): void {}
}
