<?php

declare(strict_types=1);

it('pushes live component patches over Server-Sent Events', function () {
    $basePath = realpath(__DIR__ . '/../..') ?: getcwd();
    $routePath = $basePath . '/routes/http.php';
    $routeBackup = $basePath . '/routes/http.php.e2e-backup';
    $viewsPath = $basePath . '/resources/views';
    $port = (int) getenv('APP_HTTP_PORT') ?: random_int(18000, 19999);
    $dbPath = $basePath . '/storage/e2e-sse.sqlite';

    if (file_exists($dbPath)) {
        @unlink($dbPath);
    }

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

    $route->get('/live', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        if (!schema()->hasTable('users')) {
            schema()->create('users', function ($table): void {
                $table->id();
                $table->string('email');
                $table->string('password');
                $table->string('name');
            });
        }

        $manager = app()?->container->make(\TondbadSwoole\Auth\AuthUserManager::class);
        $db = app()?->container->make(\TondbadSwoole\Database\DatabaseManager::class);
        $row = $db->table('users')->where('email', '=', 'e2e@example.com')->first();

        if ($row === null) {
            $user = $manager->create(['email' => 'e2e@example.com', 'password' => 'secret', 'name' => 'E2E']);
        } else {
            $user = new \TondbadSwoole\Auth\GenericUser('users', $row, 'id', 'password');
        }

        $session = auth('session')->login($user);

        $store = app()?->container->make(\TondbadSwoole\View\Live\StateStore::class);
        $counter = new E2ECounter();
        $counter->mount();
        $token = $store->save($counter->state());
        $html = $counter->renderView();
        $html = str_replace('<data-t-state></data-t-state>', '<input type="hidden" name="t:state" value="' . $token . '">', $html);

        $response->header('X-E2E-Session-Id', $session->accessToken->value);
        $response->header('X-E2E-Csrf-Token', (string) $session->session->antiCsrf);
        $response->html($html);
    });
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

    @unlink($basePath . '/storage/cache/routes.cache.php');

    $migrateEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => $dbPath,
        'AUTH_GUARD' => 'session',
    ]);

    $migrate = proc_open(['php', 'bin/tondbad', 'migrate'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $migratePipes, $basePath, $migrateEnv);
    proc_close($migrate);

    $cacheClear = proc_open(['php', 'bin/tondbad', 'cache:clear'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $cachePipes, $basePath, $migrateEnv);
    proc_close($cacheClear);

    $serverEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'APP_HTTP_PORT' => (string) $port,
        'APP_HTTP_HOST' => '127.0.0.1',
        'VIEW_LIVE_ENABLED' => '1',
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => $dbPath,
        'AUTH_GUARD' => 'session',
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
        expect($ready)->toBeTrue('HTTP server did not become ready in time.');

        $sseBuffer = '';
        $sseDone = false;

        go(function () use ($port, &$sseBuffer, &$sseDone): void {
            $client = new \OpenSwoole\Coroutine\Client(SWOOLE_SOCK_TCP);
            $client->set(['timeout' => 10]);

            if (!$client->connect('127.0.0.1', $port)) {
                $sseDone = true;

                return;
            }

            $client->send("GET /_live/sse?component=e2e-counter HTTP/1.1\r\nHost: 127.0.0.1:{$port}\r\nAccept: text/event-stream\r\n\r\n");

            while (!$sseDone) {
                $data = $client->recv();

                if ($data === false || $data === '') {
                    break;
                }

                $sseBuffer .= $data;

                if (str_contains($sseBuffer, '"patches"')) {
                    break;
                }
            }

            $client->close();
            $sseDone = true;
        });

        go(function () use ($port, &$sseDone): void {
            usleep(300000);

            $c = new \OpenSwoole\Coroutine\Http\Client('127.0.0.1', $port);
            $c->set(['timeout' => 10]);
            $c->setHeaders(['Accept' => '*/*']);
            $c->get('/live');

            $headers = array_change_key_case($c->getHeaders() ?: []);
            $session = $headers['x-e2e-session-id'] ?? '';
            $csrf = $headers['x-e2e-csrf-token'] ?? '';

            expect($session)->not->toBeEmpty('Session ID not returned');
            expect($csrf)->not->toBeEmpty('CSRF token not returned');

            $body = $c->getBody();
            preg_match('/name="t:state" value="([^"]+)"/', $body, $matches);
            expect($matches)->toHaveCount(2, 'State token not found in live component HTML');
            $token = $matches[1];

            $c2 = new \OpenSwoole\Coroutine\Http\Client('127.0.0.1', $port);
            $c2->set(['timeout' => 10]);
            $c2->setHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'X-CSRF-Token' => $csrf,
                'Cookie' => 'session_id=' . $session,
            ]);
            $c2->post('/_live/e2e-counter', 't:state=' . urlencode($token) . '&t:action=increment');

            expect($c2->getStatusCode())->toBe(200);
            expect($c2->getBody())->toContain('<span id="count">1</span>');

            usleep(300000);
            $sseDone = true;
        });

        \OpenSwoole\Event::wait();

        expect($sseBuffer)->toContain('text/event-stream');
        expect($sseBuffer)->toContain('data: ');
        expect($sseBuffer)->toContain('"patches"');
        expect($sseBuffer)->toContain('<span id=\\"count\\">1<\\/span>');
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
        @unlink($dbPath);

        $compiled = $basePath . '/storage/cache/views';
        foreach (glob($compiled . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
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
