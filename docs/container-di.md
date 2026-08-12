# Container & Dependency Injection

Tondbād ships a small, auto-wiring `Container` that supports bindings, singletons, contextual resolution, and scalar arguments.

## Accessing the container

```php
$container = app()->container;

$logger = $container->make(Logger::class);
```

Helper:

```php
app(); // App singleton
app()?->container->make(Logger::class);
```

## Binding

```php
use TondbadSwoole\Contracts\Mailer;
use App\Services\SmtpMailer;

$container->bind(Mailer::class, SmtpMailer::class);
```

## Singletons

```php
$container->singleton(Config::class, function () {
    return new Config(...);
});
```

## Auto-wiring

The container resolves constructor dependencies automatically:

```php
class ReportController
{
    public function __construct(
        private readonly ReportService $reports,
    ) {
    }
}
```

`$container->make(ReportController::class)` resolves `ReportService` recursively.

## Scalar parameters

For scalar constructor parameters, bind a closure that supplies the values:

```php
$container->bind(MyService::class, function () use ($container) {
    return new MyService(
        $container->make(ApiClient::class),
        __DIR__ . '/../storage',
    );
});
```

Or use the `Container::call` helper for action methods and inject named route variables:

```php
$container->call([$controller, 'index'], ['id' => 5]);
```

## Contextual resolution

Request-scoped state is stored in `ContextInterface`:

```php
$context = app()->container->make(ContextInterface::class);
$context->set('current_user', $user);

$user = $context->get('current_user');
```

The `ContextInterface` is cleared automatically at the end of each request, so pooled resources and per-request state do not leak across coroutines.
