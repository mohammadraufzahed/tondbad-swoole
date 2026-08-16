<?php

declare(strict_types=1);

beforeAll(function () {
    if (!extension_loaded('openswoole')) {
        markTestSkipped('OpenSwoole extension not loaded.');
    }
});

it('filters comments by morphTo has, whereHas, and doesntHave', function () {
    $port = (int) getenv('APP_HTTP_PORT') ?: 19511;
    $dbFile = sys_get_temp_dir() . '/tondbad_morph_' . uniqid() . '.sqlite';

    exec('php bin/tondbad cache:clear 2>&1', $cacheOutput, $cacheCode);

    $cmd = ['php', 'bin/tondbad', 'serve'];
    $env = array_merge(
        $_ENV,
        [
            'APP_TYPE' => 'http',
            'APP_ENV' => 'testing',
            'APP_HTTP_PORT' => (string) $port,
            'APP_HTTP_HOST' => '127.0.0.1',
            'DB_CONNECTION' => 'sqlite',
            'DB_SQLITE_DATABASE' => $dbFile,
            'ROUTES_HTTP' => 'tests/E2E/morph_routes.php',
        ],
    );

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, getcwd(), $env);
    expect($process)->toBeResource();

    $ready = false;
    for ($i = 0; $i < 30; ++$i) {
        usleep(200000);
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if ($fp) {
            fclose($fp);
            $ready = true;

            break;
        }
    }
    expect($ready)->toBeTrue('HTTP server did not become ready in time.');

    $base = "http://127.0.0.1:{$port}";

    $setup = @file_get_contents($base . '/setup', false, stream_context_create(['http' => ['timeout' => 5]]));
    expect($setup)->toBe('OK');

    $has = @file_get_contents($base . '/morph-has', false, stream_context_create(['http' => ['timeout' => 5]]));
    expect(json_decode($has, true))->toBe(['First', 'Second']);

    $whereHas = @file_get_contents($base . '/morph-where-has', false, stream_context_create(['http' => ['timeout' => 5]]));
    expect(json_decode($whereHas, true))->toBe(['First']);

    $doesntHave = @file_get_contents($base . '/morph-doesnt-have', false, stream_context_create(['http' => ['timeout' => 5]]));
    expect(json_decode($doesntHave, true))->toBe(['Third']);

    proc_terminate($process, SIGTERM);
    proc_close($process);

    @unlink($dbFile);
});
