<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\BenchmarkRunner;
use TondbadSwoole\Benchmark\Scenario;

$payload = json_decode(base64_decode($argv[1] ?? ''), true);

$autoload = $payload['autoload'] ?? __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    fwrite(STDERR, "Autoload not found: {$autoload}\n");
    exit(1);
}

require $autoload;

$scenarioData = $payload['scenario'] ?? null;

if (!is_array($scenarioData)) {
    fwrite(STDERR, "No scenario provided.\n");
    exit(1);
}

$scenario = Scenario::fromExport($scenarioData);
$result = (new BenchmarkRunner())->run($scenario);

echo json_encode($result->toArray());
