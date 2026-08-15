<?php

declare(strict_types=1);

beforeAll(function () {
    if (!extension_loaded('openswoole')) {
        markTestSkipped('OpenSwoole extension not loaded.');
    }
});

it('replies to a unary grpc call through serve:grpc', function () {
    $port = (int) getenv('APP_GRPC_PORT') ?: 19508;
    $cmd = ['php', 'bin/tondbad', 'serve:grpc'];

    $env = array_merge(
        $_ENV,
        [
            'APP_TYPE' => 'grpc',
            'APP_ENV' => 'testing',
            'APP_GRPC_PORT' => (string) $port,
            'APP_GRPC_HOST' => '127.0.0.1',
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
    expect($ready)->toBeTrue('gRPC server did not become ready in time.');

    $clientCmd = ['php', 'tests/E2E/grpc_client.php', (string) $port];
    $client = proc_open($clientCmd, $descriptors, $clientPipes, getcwd(), $env);
    stream_set_timeout($clientPipes[1], 10);
    stream_set_timeout($clientPipes[2], 10);

    $output = trim(stream_get_contents($clientPipes[1]) ?: '');
    $errors = trim(stream_get_contents($clientPipes[2]) ?: '');

    fclose($clientPipes[0]);
    fclose($clientPipes[1]);
    fclose($clientPipes[2]);
    proc_close($client);

    proc_terminate($process, SIGTERM);
    proc_close($process);

    if ($errors !== '') {
        echo "Client stderr: {$errors}\n";
    }

    expect($output)->toStartWith('OK:');
    expect(str_replace('OK:', '', $output))->toBe('Hello, E2E');
});
