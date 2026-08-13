<?php

declare(strict_types=1);

it('runs scheduled events with the schedule:work command', function () {
    $script = __DIR__ . '/../../Support/ScheduleWorkScript.php';
    $output = shell_exec('php ' . escapeshellarg($script) . ' 2>&1');

    expect($output)->not->toBeNull();

    $result = json_decode($output, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
    expect($result['exitCode'] ?? 1)->toBe(0);
    expect($result['ran'] ?? false)->toBeTrue();
});
