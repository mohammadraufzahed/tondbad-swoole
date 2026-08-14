<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Predis\Client;
use TondbadSwoole\Queue\Drivers\RedisQueue;
use TondbadSwoole\Tests\Support\QueueWorkConcurrencyJob;

putenv('QUEUE_REDIS_DATABASE=11');

$client = new Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
    'database' => 11,
]);

$client->flushdb();

$queue = new RedisQueue($client, 'tondbad', 'default', 60, 0);

$pattern = sys_get_temp_dir() . '/tondbad_qw_*.marker';
foreach (glob($pattern) as $file) {
    unlink($file);
}

$jobIds = [];
for ($i = 0; $i < 10; $i++) {
    $job = new QueueWorkConcurrencyJob($i);
    $jobIds[$queue->add($job)] = true;
}

$bin = __DIR__ . '/../../bin/tondbad';
$cmd = 'php ' . escapeshellarg($bin) . ' queue:work --connection=redis --queue=default --concurrency=4 --max-jobs=10 --stop-when-empty 2>&1';
exec($cmd, $output, $exitCode);

$files = glob($pattern);
$processed = [];
$prefix = 'tondbad_qw_';
foreach ($files as $file) {
    $base = basename($file, '.marker');
    if (str_starts_with($base, $prefix)) {
        $jobId = substr($base, strlen($prefix));
        $processed[$jobId] = (int) file_get_contents($file);
    }
}

echo json_encode([
    'exitCode' => $exitCode,
    'output' => implode("\n", $output),
    'expectedJobIds' => array_keys($jobIds),
    'processedJobIds' => array_keys($processed),
    'processedIndexes' => array_values($processed),
    'count' => count($processed),
    'unique' => count($processed) === count(array_unique($processed)),
]);

foreach ($files as $file) {
    unlink($file);
}
