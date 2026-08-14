<?php

declare(strict_types=1);

function queueWorkRedisAvailable(): bool
{
    static $available = null;

    if ($available === null) {
        try {
            (new Predis\Client(['host' => '127.0.0.1', 'port' => 6379, 'database' => 11]))->ping();
            $available = true;
        } catch (Throwable) {
            $available = false;
        }
    }

    return $available;
}

it('processes redis jobs concurrently with queue:work without duplicates', function () {
    $script = __DIR__ . '/../../Support/QueueWorkConcurrencyScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $result = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($result['exitCode'] ?? 1)->toBe(0);
    expect($result['count'] ?? 0)->toBe(10);
    expect($result['unique'] ?? false)->toBeTrue();
    expect(array_diff($result['expectedJobIds'] ?? [], $result['processedJobIds'] ?? []))->toBeEmpty();
})->skip(fn () => !queueWorkRedisAvailable(), 'Redis not available');
