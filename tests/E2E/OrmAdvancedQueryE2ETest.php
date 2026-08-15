<?php

declare(strict_types=1);

beforeAll(function () {
    if (!extension_loaded('openswoole')) {
        markTestSkipped('OpenSwoole extension not loaded.');
    }
});

it('exercises the ORM advanced query layer over HTTP', function () {
    $basePath = realpath(__DIR__ . '/../..') ?: getcwd();
    $routePath = $basePath . '/routes/http.php';
    $routeBackup = $basePath . '/routes/http.php.e2e-backup';
    $dbFile = sys_get_temp_dir() . '/tondbad_orm_e2e_' . uniqid() . '.sqlite';
    $port = (int) getenv('APP_HTTP_PORT') ?: random_int(18000, 19999);

    $originalRoute = file_exists($routePath) ? file_get_contents($routePath) : null;

    if ($originalRoute !== null) {
        file_put_contents($routeBackup, $originalRoute);
    }

    $routesDir = dirname($routePath);
    if (!is_dir($routesDir)) {
        mkdir($routesDir, 0755, true);
    }

    file_put_contents($routePath, <<<'PHP'
<?php

return function (\TondbadSwoole\Core\Route\Route $route) {
    class E2EUser extends \TondbadSwoole\Database\Model
    {
        protected ?string $table = 'e2e_users';
        protected array $fillable = ['name', 'settings'];
        public bool $timestamps = false;

        public function posts()
        {
            return $this->hasMany(\E2EPost::class, 'user_id', 'id');
        }
    }

    class E2EPost extends \TondbadSwoole\Database\Model
    {
        protected ?string $table = 'e2e_posts';
        protected array $fillable = ['user_id', 'title'];
        public bool $timestamps = false;
    }

    $route->get('/setup', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        schema()->dropIfExists('e2e_posts');
        schema()->dropIfExists('e2e_users');

        schema()->create('e2e_users', function (\TondbadSwoole\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('settings')->nullable();
        });

        schema()->create('e2e_posts', function (\TondbadSwoole\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
        });

        $u1 = \E2EUser::create(['name' => 'Alice', 'settings' => json_encode(['role' => 'admin'])]);
        $u2 = \E2EUser::create(['name' => 'Bob', 'settings' => json_encode(['role' => 'user'])]);

        \E2EPost::create(['user_id' => $u1->id, 'title' => 'First post']);
        \E2EPost::create(['user_id' => $u1->id, 'title' => 'Second post']);
        \E2EPost::create(['user_id' => $u2->id, 'title' => 'Third post']);

        $response->json(['ok' => true]);
    });

    $route->get('/with-count', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $users = \E2EUser::withCount('posts')->orderBy('id')->get();
        $result = [];
        foreach ($users as $user) {
            $result[] = ['name' => $user->name, 'posts_count' => $user->posts_count];
        }
        $response->json($result);
    });

    $route->get('/with-posts', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $users = \E2EUser::with('posts')->orderBy('id')->get();
        $result = [];
        foreach ($users as $user) {
            $posts = [];
            foreach ($user->posts as $post) {
                $posts[] = ['title' => $post->title];
            }
            $result[] = ['name' => $user->name, 'posts' => $posts];
        }
        $response->json($result);
    });

    $route->get('/has-posts', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $users = \E2EUser::has('posts', '>=', 2)->orderBy('id')->get();
        $result = [];
        foreach ($users as $user) {
            $result[] = ['name' => $user->name];
        }
        $response->json($result);
    });

    $route->get('/where-has', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $users = \E2EUser::whereHas('posts', function ($query) {
            $query->where('title', 'like', '%First%');
        })->orderBy('id')->get();
        $result = [];
        foreach ($users as $user) {
            $result[] = ['name' => $user->name];
        }
        $response->json($result);
    });

    $route->get('/json-contains', function (\TondbadSwoole\Http\Request $request, \TondbadSwoole\Http\Response $response) {
        $users = \E2EUser::whereJsonContains('settings->role', 'admin')->get();
        $result = [];
        foreach ($users as $user) {
            $result[] = ['name' => $user->name];
        }
        $response->json($result);
    });
};
PHP
);

    $cacheEnv = array_merge($_ENV, [
        'APP_TYPE' => 'http',
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => $dbFile,
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
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => $dbFile,
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
        $setup = fetchE2EJson("http://127.0.0.1:{$port}/setup");
        expect($setup)->toBe(['ok' => true]);

        $withCount = fetchE2EJson("http://127.0.0.1:{$port}/with-count");
        expect($withCount)->toBe([
            ['name' => 'Alice', 'posts_count' => 2],
            ['name' => 'Bob', 'posts_count' => 1],
        ]);

        $withPosts = fetchE2EJson("http://127.0.0.1:{$port}/with-posts");
        expect($withPosts[0]['name'])->toBe('Alice');
        expect($withPosts[0]['posts'])->toHaveCount(2);
        expect($withPosts[1]['posts'])->toHaveCount(1);

        $hasPosts = fetchE2EJson("http://127.0.0.1:{$port}/has-posts");
        expect($hasPosts)->toHaveCount(1);
        expect($hasPosts[0]['name'])->toBe('Alice');

        $whereHas = fetchE2EJson("http://127.0.0.1:{$port}/where-has");
        expect($whereHas)->toHaveCount(1);
        expect($whereHas[0]['name'])->toBe('Alice');

        $jsonContains = fetchE2EJson("http://127.0.0.1:{$port}/json-contains");
        expect($jsonContains)->toHaveCount(1);
        expect($jsonContains[0]['name'])->toBe('Alice');
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

        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        if ($originalRoute !== null) {
            file_put_contents($routePath, $originalRoute);
            @unlink($routeBackup);
        } else {
            @unlink($routePath);
        }

        @unlink($basePath . '/storage/cache/routes.cache.php');
    }
});

function fetchE2EJson(string $url): mixed
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    expect($body)->not->toBeFalse('HTTP request failed: ' . $url);

    return json_decode($body, true);
}
