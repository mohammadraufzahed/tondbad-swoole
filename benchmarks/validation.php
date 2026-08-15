<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use TondbadSwoole\Validation\Schema;
use TondbadSwoole\Validation\Validator;

$iterations = 10_000;

$schema = Schema::object([
    'email' => Schema::string()->email()->required(),
    'password' => Schema::string()->min(8)->required(),
    'age' => Schema::int()->coerce()->gte(18)->nullable()->default(null),
    'tags' => Schema::array(Schema::string())->max(10)->default([]),
])->lax();

$validData = [
    'email' => 'user@example.com',
    'password' => 'password123',
    'age' => '25',
    'tags' => ['php', 'swoole'],
];

$rules = [
    'email' => 'required|email',
    'password' => 'required|min:8',
    'age' => 'nullable|int|gte:18',
    'tags' => 'array',
];

// Warm up
$schema->safeParse($validData);
new Validator($validData, $rules);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $schema->safeParse($validData);
}
$schemaTime = microtime(true) - $start;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $validator = new Validator($validData, $rules);
    $validator->passes();
}
$validatorTime = microtime(true) - $start;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $schema->safeParse($validData);
    $schema->safeParse($validData);
}
$combinedTime = microtime(true) - $start;

$peakMemory = memory_get_peak_usage(true) / 1024 / 1024;

printf("Validation benchmark (%d iterations)\n", $iterations);
printf("Schema (safeParse):   %.4f s (%.2f μs/iter)\n", $schemaTime, $schemaTime * 1_000_000 / $iterations);
printf("Legacy Validator:    %.4f s (%.2f μs/iter)\n", $validatorTime, $validatorTime * 1_000_000 / $iterations);
printf("Combined (2x Schema): %.4f s\n", $combinedTime);
printf("Peak memory:          %.2f MB\n", $peakMemory);

if ($schemaTime < $validatorTime) {
    printf("Schema is %.2fx faster than legacy Validator\n", $validatorTime / $schemaTime);
} else {
    printf("Legacy Validator is %.2fx faster than Schema\n", $schemaTime / $validatorTime);
}
