# Tondbād Swoole Documentation

Tondbād Swoole is a lightweight, async-first PHP framework built on OpenSwoole. It borrows developer-friendly conventions from Laravel while staying small, explicit, and Swoole-native.

## Sections

- [Getting Started](getting-started.md) — installation, bootstrap, and project structure
- [Configuration](configuration.md) — config files and environment mapping
- [Container & Dependency Injection](container-di.md) — the `Container`, binding, and resolution
- [Service Providers](providers.md) — registering and booting framework extensions
- [Routing](routing.md) — HTTP routing, groups, named routes, and model binding
- [Controllers & Requests](controllers.md) — controllers, middleware, and form requests
- [gRPC](grpc.md) — running gRPC services
- [Database](database.md) — connection pool, `DB` helper, and raw queries
- [ORM](orm.md) — models, relations, `EntityManager`, migrations, and `SchemaTool`
- [Cache](cache.md) — in-memory, Redis, and Predis cache drivers
- [Queue & Jobs](queue.md) — jobs, dispatch, workers, and failed jobs
- [Task Scheduling](scheduling.md) — cron-based scheduler and worker process
- [Events & Listeners](events.md) — event dispatcher and queued listeners
- [Validation](validation.md) — rules, messages, and `FormRequest`
- [Authentication & Authorization](authentication.md) — guards, providers, gates, and policies
- [Console](console.md) — the `tondbad` CLI and generator commands
- [Testing](testing.md) — Pest, fixtures, and in-memory database tests
- [Deployment](deployment.md) — OpenSwoole server, reload, and shutdown

## Design goals

- **Coroutine-safe**: every request gets its own `Context`, and pooled resources are returned per coroutine.
- **Explicit over magic**: models, routes, providers, and commands are declared.
- **Borrow Laravel conventions**: `artisan`-style CLI, Eloquent-like models, and config-driven providers — without copying Laravel internals.
- **Own your skeleton**: the package ships the framework; the consumer owns `app/`, `config/`, `routes/`, `database/migrations/`, and `public/`.
