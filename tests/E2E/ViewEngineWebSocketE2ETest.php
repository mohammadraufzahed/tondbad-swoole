<?php

declare(strict_types=1);

it('renders and updates a live component over WebSocket', function () {
    $basePath = realpath(__DIR__ . '/../..') ?: getcwd();
    $routePath = $basePath . '/routes/http.php';
    $routeBackup = $basePath . '/routes/http.php.e2e-backup';
    $viewsPath = $basePath . '/resources/views';
    $port = (int) getenv('APP_HTTP_PORT') ?: random_int(18000, 19999);

    $originalRoute = file_exists($routePath) ? file_get_contents($routePath) : null;

    if ($originalRoute !== null) {
        file_put_contents($routeBackup, $originalRoute);
    }

    file_put_contents($routePath, <<<'PHP'
<?php

return function (\TondbadSwoole\Core\Route\Route $route) {
    class E2ECounter extends \TondbadSwoole\View\Live\LiveComponent
    {
        public int $count = 0;

        public function increment(): void
        {
            $this->count++;
        }

        public function render(): \TondbadSwoole\View\View
        {
            return view('components.e2e-counter', ['count' => $this->count]);
        }
    }

    app()?->container->make(\TondbadSwoole\View\ViewManager::class)->registerComponent('e2e-counter', E2ECounter::class);
};
PHP
);

    ensureE2EView($viewsPath . '/components/e2e-counter.tond.php', <<<'VIEW'
<div data-t-live="e2e-counter">
    <span id="count">{{ $count }}</span>
    <data-t-state></data-t-state>
</div>
VIEW
);

    $serverEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'APP_HTTP_PORT' => (string) $port,
        'APP_HTTP_HOST' => '127.0.0.1',
        'VIEW_LIVE_ENABLED' => '1',
        'VIEW_LIVE_TRANSPORT' => 'websocket',
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => ':memory:',
        'AUTH_GUARD' => 'access_token',
    ]);

    $server = proc_open(['php', 'bin/tondbad', 'serve'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $serverPipes, $basePath, $serverEnv);

    stream_set_blocking($serverPipes[1], false);
    stream_set_blocking($serverPipes[2], false);

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

    try {
        expect($ready)->toBeTrue('WebSocket server did not become ready in time.');

        $result = null;

        go(function () use ($port, &$result): void {
            $client = new \OpenSwoole\Coroutine\Http\Client('127.0.0.1', $port);
            $client->set(['timeout' => 3]);

            if (!$client->upgrade('/_live/ws')) {
                $result = ['error' => 'upgrade failed'];

                return;
            }

            $client->push(json_encode(['t:component' => 'e2e-counter']));
            $frame = $client->recv();
            $data = json_decode((string) ($frame?->data ?? '{}'), true);

            expect($data['patches'] ?? [])->toHaveCount(1);
            expect($data['patches'][0]['html'] ?? '')->toContain('<span id="count">0</span>');

            $token = $data['token'] ?? '';
            expect($token)->not->toBeEmpty();

            $client->push(json_encode([
                't:component' => 'e2e-counter',
                't:state' => $token,
                't:action' => 'increment',
            ]));

            $frame2 = $client->recv();
            $data2 = json_decode((string) ($frame2?->data ?? '{}'), true);

            expect($data2['patches'][0]['html'] ?? '')->toContain('<span id="count">1</span>');

            $client->close();

            $result = ['ok' => true];
        });

        \OpenSwoole\Event::wait();

        expect($result)->toBe(['ok' => true]);
    } finally {
        @proc_terminate($server, SIGTERM);

        $timeout = microtime(true) + 2.0;
        while (microtime(true) < $timeout) {
            $status = @proc_get_status($server);
            if (!$status || !$status['running']) {
                break;
            }
            usleep(100000);
        }

        if ($status['running'] ?? false) {
            @proc_terminate($server, SIGKILL);
        }

        @proc_close($server);

        if ($originalRoute !== null) {
            file_put_contents($routePath, $originalRoute);
            @unlink($routeBackup);
        } else {
            @unlink($routePath);
        }

        @unlink($viewsPath . '/components/e2e-counter.tond.php');
    }
});

if (!function_exists('ensureE2EView')) {
    function ensureE2EView(string $path, string $content): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
    }
}
