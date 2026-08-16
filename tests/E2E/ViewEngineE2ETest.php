<?php

declare(strict_types=1);

beforeAll(function () {
    if (!extension_loaded('openswoole')) {
        markTestSkipped('OpenSwoole extension not loaded.');
    }
});

it('renders compiled views with layouts, components, and live fragments over HTTP', function () {
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

    $route->view('/welcome', 'e2e-welcome', ['name' => 'Tond']);

    $route->get('/live', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $store = app()?->container->make(\TondbadSwoole\View\Live\StateStore::class);
        $counter = new E2ECounter();
        $counter->mount();
        $token = $store->save($counter->state());
        $html = $counter->renderView();
        $html = str_replace('<data-t-state></data-t-state>', '<input type="hidden" name="t:state" value="' . $token . '">', $html);

        $response->html($html);
    });
};
PHP
);

    ensureE2EView($viewsPath . '/e2e-layout.tond.php', <<<'VIEW'
<!doctype html>
<html>
<head><title>@yield('title', 'E2E')</title></head>
<body>@yield('content')</body>
</html>
VIEW
);

    ensureE2EView($viewsPath . '/e2e-welcome.tond.php', <<<'VIEW'
@extends('e2e-layout')
@section('title', 'Welcome E2E')
@section('content')
<x-e2e-alert type="success">Hello, {{ $name }}</x-e2e-alert>
@endsection
VIEW
);

    ensureE2EView($viewsPath . '/components/e2e-alert.tond.php', <<<'VIEW'
@props(['type' => 'info'])
<div class="alert alert-{{ $type }}">{{ $slot() }}</div>
VIEW
);

    ensureE2EView($viewsPath . '/components/e2e-counter.tond.php', <<<'VIEW'
<div data-t-live="e2e-counter">
    <span id="count">{{ $count }}</span>
    <data-t-state></data-t-state>
</div>
VIEW
);

    $cacheEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'VIEW_LIVE_ENABLED' => '1',
        'VIEW_LIVE_TRANSPORT' => 'http',
    ]);

    $cacheClear = proc_open(['php', 'bin/tondbad', 'cache:clear'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $cachePipes, $basePath, $cacheEnv);
    proc_close($cacheClear);

    $serverEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'APP_HTTP_PORT' => (string) $port,
        'APP_HTTP_HOST' => '127.0.0.1',
        'VIEW_LIVE_ENABLED' => '1',
        'VIEW_LIVE_TRANSPORT' => 'http',
    ]);

    $server = proc_open(['php', 'bin/tondbad', 'serve'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $serverPipes, $basePath, $serverEnv);
    stream_set_blocking($serverPipes[1], false);
    stream_set_blocking($serverPipes[2], false);

    $serverOut = '';
    $serverErr = '';
    $ready = false;
    for ($i = 0; $i < 30; ++$i) {
        usleep(200000);
        $serverOut .= (string) stream_get_contents($serverPipes[1]);
        $serverErr .= (string) stream_get_contents($serverPipes[2]);
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if ($fp) {
            fclose($fp);
            $ready = true;

            break;
        }
    }

    if (!$ready) {
        fwrite(STDERR, "Server stdout:\n{$serverOut}\n");
        fwrite(STDERR, "Server stderr:\n{$serverErr}\n");
    }

    expect($ready)->toBeTrue('HTTP server did not become ready in time.');

    try {
        $welcome = fetchE2EUrl("http://127.0.0.1:{$port}/welcome");
        expect($welcome)->toContain('Hello, Tond')
            ->and($welcome)->toContain('alert-success')
            ->and($welcome)->toContain('Welcome E2E');

        $live = fetchE2EUrl("http://127.0.0.1:{$port}/live");
        expect($live)->toContain('data-t-live="e2e-counter"')
            ->and($live)->toContain('<span id="count">0</span>');

        preg_match('/name="t:state" value="([^"]+)"/', $live, $matches);
        expect($matches)->toHaveCount(2, 'State token not found in live component HTML');
        $token = $matches[1];

        $updated = fetchE2EUrl("http://127.0.0.1:{$port}/_live/e2e-counter", [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query(['t:state' => $token, 't:action' => 'increment']),
                'timeout' => 5,
            ],
        ]);
        expect($updated)->toContain('<span id="count">1</span>')
            ->and($updated)->toContain('name="t:state"');
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

        @unlink($viewsPath . '/e2e-layout.tond.php');
        @unlink($viewsPath . '/e2e-welcome.tond.php');
        @unlink($viewsPath . '/components/e2e-alert.tond.php');
        @unlink($viewsPath . '/components/e2e-counter.tond.php');

        $compiled = $basePath . '/storage/cache/views';
        foreach (glob($compiled . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }
});

function ensureE2EView(string $path, string $content): void
{
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($path, $content);
}

function fetchE2EUrl(string $url, ?array $contextOptions = null): string
{
    $context = stream_context_create($contextOptions ?? [
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    expect($body)->not->toBeFalse('HTTP request failed: ' . $url);

    return (string) $body;
}
