<?php

declare(strict_types=1);

it('keeps coroutine context isolated between concurrent coroutines', function () {
    $script = __DIR__ . '/../../Support/ContextIsolationScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $results = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($results)->toBeArray()->toHaveCount(2);
    expect($results)->toContain('a');
    expect($results)->toContain('b');
});
