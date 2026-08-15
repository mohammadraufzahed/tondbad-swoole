<?php

declare(strict_types=1);

use OpenSwoole\GRPC\Context;
use OpenSwoole\GRPC\Constant as GrpcConstant;
use OpenSwoole\GRPC\Request as GrpcRequest;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Response as GrpcResponse;
use OpenSwoole\GRPC\Status;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Events\AuthEvent;
use TondbadSwoole\Auth\GenericUser;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Console\Application;
use TondbadSwoole\Console\Events\ConsoleEvent;
use TondbadSwoole\Core\Cache\Events\CacheEvent;
use TondbadSwoole\Core\Route\Events\RouteEvent;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Database\Attributes\Column;
use TondbadSwoole\Database\Attributes\Entity;
use TondbadSwoole\Database\Attributes\Id;
use TondbadSwoole\Database\Events\OrmEvent;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Events\Event;
use TondbadSwoole\GRPC\Events\GrpcEvent;
use TondbadSwoole\GRPC\RouteGrpcMiddleware;
use TondbadSwoole\Queue\Events\QueueEvent;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Scheduling\Events\ScheduleEvent;
use TondbadSwoole\Scheduling\Schedule;

beforeEach(function () {
    $this->originalEnv = $_ENV;

    $_ENV = [
        'APP_TYPE' => 'http',
        'APP_DEBUG' => 'false',
        'DB_CONNECTION' => 'sqlite',
        'DB_SQLITE_DATABASE' => ':memory:',
        'CACHE_DEFAULT' => 'in-memory',
        'QUEUE_DEFAULT' => 'database',
        'AUTH_GUARD' => 'session',
        'AUTH_SESSION_STORE' => 'database',
    ];

    $this->app = new App(__DIR__ . '/../../../..');
    $this->dispatcher = $this->app->container->make(EventDispatcher::class);

    $this->captured = [];

    db()->getPdo()->exec('CREATE TABLE jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        queue TEXT NOT NULL,
        payload TEXT NOT NULL,
        attempts INTEGER NOT NULL,
        reserved_at INTEGER,
        available_at INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        priority INTEGER DEFAULT 0,
        delay INTEGER,
        backoff_type TEXT,
        backoff_value INTEGER,
        timeout INTEGER,
        progress INTEGER DEFAULT 0,
        deduplication_id TEXT,
        parent_id INTEGER,
        children_count INTEGER DEFAULT 0,
        completed_children_count INTEGER DEFAULT 0,
        result TEXT,
        status TEXT DEFAULT "waiting"
    )');

    db()->getPdo()->exec('CREATE TABLE queue_pauses (
        queue TEXT PRIMARY KEY,
        paused INTEGER DEFAULT 0
    )');

    db()->getPdo()->exec('CREATE TABLE failed_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        connection TEXT,
        queue TEXT,
        payload TEXT,
        exception TEXT,
        failed_at INTEGER
    )');

    db()->getPdo()->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        email TEXT,
        settings TEXT,
        is_admin INTEGER DEFAULT 0,
        created_at INTEGER,
        updated_at INTEGER
    )');

    db()->getPdo()->exec('CREATE TABLE sessions (
        id TEXT PRIMARY KEY,
        user_id TEXT NOT NULL,
        claims TEXT NOT NULL,
        anti_csrf TEXT,
        device TEXT,
        family TEXT,
        status TEXT DEFAULT "active",
        expires_at INTEGER NOT NULL,
        created_at INTEGER NOT NULL
    )');

    db()->getPdo()->exec('CREATE TABLE refresh_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL,
        family TEXT NOT NULL,
        parent INTEGER,
        token_hash TEXT NOT NULL UNIQUE,
        used_at INTEGER,
        revoked INTEGER DEFAULT 0,
        expires_at INTEGER NOT NULL,
        created_at INTEGER NOT NULL
    )');

    db()->getPdo()->exec('CREATE TABLE mfa_factors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        type TEXT NOT NULL,
        config TEXT NOT NULL,
        created_at INTEGER,
        updated_at INTEGER
    )');
});

afterEach(function () {
    $_ENV = $this->originalEnv;

    if (class_exists(\OpenSwoole\Timer::class) && method_exists(\OpenSwoole\Timer::class, 'clearAll')) {
        \OpenSwoole\Timer::clearAll();
    }

    $this->app?->container->make(\TondbadSwoole\Contracts\ContextInterface::class)->clear();
});

it('dispatches queue lifecycle events through the event bus', function () {
    $this->dispatcher->listen(QueueEvent::class, function (QueueEvent $event) {
        $this->captured[] = $event->name();
    });

    queue()->push(new FrameworkEventJob(), 'default');

    expect($this->captured)->toContain('queue.job.added');
});

it('dispatches orm lifecycle events through the event bus', function () {
    schema()->create('products', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->dispatcher->listen(OrmEvent::class, function (OrmEvent $event) {
        $this->captured[] = $event->name();
    });

    $entity = new FrameworkEventProduct();
    $entity->forceFill(['name' => 'Widget']);

    em()->persist($entity);
    em()->flush();

    expect($this->captured)->toContain('orm.postPersist');
    expect($this->captured)->toContain('orm.onFlush');
});

it('dispatches auth lifecycle events through the event bus', function () {
    $this->dispatcher->listen(AuthEvent::class, function (AuthEvent $event) {
        $this->captured[] = $event->name();
    });

    $swoole = new SwooleRequest();
    $swoole->server = ['request_method' => 'GET', 'request_uri' => '/'];
    $swoole->header = [];
    $request = new \TondbadSwoole\Http\Request($swoole);
    auth()->setRequest($request);

    $user = new GenericUser('users', ['id' => 1, 'email' => 'test@example.com'], 'id', 'password');

    auth()->login($user, 'session');

    expect($this->captured)->toContain('auth.login');

    expect(auth()->user('session'))->not->toBeNull();

    auth()->logout('session');

    expect($this->captured)->toContain('auth.logout');
});

it('dispatches cache lifecycle events through the event bus', function () {
    $this->dispatcher->listen(CacheEvent::class, function (CacheEvent $event) {
        $this->captured[] = $event->name();
    });

    $captured = &$this->captured;
    $cache = cache();

    Coroutine::run(function () use (&$captured, $cache) {
        $value = $cache->getOrSet('integration-key', fn () => 'integration-value', 60);

        expect($value)->toBe('integration-value');
        expect($captured)->toContain('cache.miss');
        expect($captured)->toContain('cache.set');

        $value = $cache->getOrSet('integration-key', fn () => 'other', 60);

        expect($value)->toBe('integration-value');
        expect($captured)->toContain('cache.hit');

        $cache->delete('integration-key');

        expect($captured)->toContain('cache.delete');
    });
});

it('dispatches route lifecycle events through the event bus', function () {
    $this->dispatcher->listen(RouteEvent::class, function (RouteEvent $event) {
        $this->captured[] = $event->name();
    });

    $route = $this->app->container->make(Route::class);
    $route->get('/events-integration', function (TondbadSwoole\Http\Request $req, TondbadSwoole\Http\Response $res) {
        // no-op handler to avoid detached OpenSwoole response warnings in CLI
    });

    $swooleRequest = new SwooleRequest();
    $swooleRequest->server = ['request_method' => 'GET', 'request_uri' => '/events-integration'];

    $swooleResponse = new SwooleResponse();

    $route->getRouteDispatcher()->dispatch($swooleRequest, $swooleResponse);

    expect($this->captured)->toContain('route.dispatching');
    expect($this->captured)->toContain('route.matched');
    expect($this->captured)->toContain('route.dispatched');
});

it('dispatches console lifecycle events through the event bus', function () {
    $this->dispatcher->listen(ConsoleEvent::class, function (ConsoleEvent $event) {
        $this->captured[] = $event->name();
    });

    $console = $this->app->container->make(Application::class);
    ob_start();
    $console->run(['tondbad', 'unknown-command-for-events']);
    ob_end_clean();

    expect($this->captured)->toContain('console.not_found');
});

it('dispatches schedule lifecycle events through the event bus', function () {
    $this->dispatcher->listen(ScheduleEvent::class, function (ScheduleEvent $event) {
        $this->captured[] = $event->name();
    });

    $schedule = $this->app->container->make(Schedule::class);
    $schedule->call(fn () => 'ran')->everyMinute();

    $schedule->runDueEvents(new DateTimeImmutable('2026-01-01 12:00:00'));

    expect($this->captured)->toContain('schedule.starting');
    expect($this->captured)->toContain('schedule.ran');
});

it('dispatches grpc lifecycle events through the event bus', function () {
    $this->dispatcher->listen(GrpcEvent::class, function (GrpcEvent $event) {
        $this->captured[] = $event->name();
    });

    $route = $this->app->container->make(Route::class);
    $route->post('/greeter.Greeter/SayHello', function (TondbadSwoole\Http\Request $req, TondbadSwoole\Http\Response $res) {
        $res->json(['message' => 'hello']);
    });

    $dispatcher = $route->getRouteDispatcher();
    $middleware = new RouteGrpcMiddleware($dispatcher, $this->dispatcher);

    $rawRequest = new SwooleRequest();
    $rawRequest->server = ['request_uri' => '/greeter.Greeter/SayHello', 'request_method' => 'POST', 'remote_addr' => '127.0.0.1', 'remote_port' => '12345'];
    $rawRequest->header = ['content-type' => 'application/grpc+json'];

    $context = new Context([
        \OpenSwoole\Http\Request::class => $rawRequest,
        \OpenSwoole\Http\Response::class => new SwooleResponse(),
        GrpcConstant::CONTENT_TYPE => 'application/grpc+json',
        GrpcConstant::GRPC_STATUS => Status::UNKNOWN,
        GrpcConstant::GRPC_MESSAGE => '',
    ]);

    $request = new GrpcRequest($context, '/greeter.Greeter', 'SayHello', json_encode(['name' => 'World']));

    $handler = new class implements RequestHandlerInterface {
        public function handle(GrpcRequest $request): GrpcResponse
        {
            return new GrpcResponse($request->getContext(), '');
        }
    };

    $middleware->process($request, $handler);

    expect($this->captured)->toContain('grpc.request');
    expect($this->captured)->toContain('grpc.response');
});

class FrameworkEventJob extends Job
{
    public function handle(): void {}
}

#[Entity('products')]
class FrameworkEventProduct extends Model
{
    protected ?string $table = 'products';

    #[Id]
    protected int $id;

    #[Column]
    protected string $name;

    protected array $fillable = ['name'];
}
