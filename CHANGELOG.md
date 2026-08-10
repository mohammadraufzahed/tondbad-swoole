# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- PSR-16 aligned cache drivers: `InMemoryCache`, `PredisCache`, and `PhpRedisCache`.
- PHPUnit test suite with coverage for config, environment, container resolution, pipeline, and route caching.
- GitHub Actions CI workflow running `composer validate`, `php -l`, and `composer test`.
- Graceful shutdown for the OpenSwoole HTTP server using `pcntl_signal`.
- Reflection metadata caching in `Container`.
- Compiled route cache using `FastRoute\cachedDispatcher` with serializable handler IDs.
- `TondbadSwoole\Http\Request` and `TondbadSwoole\Http\Response` wrapper classes with helper methods.
- Real HTTP `CorsMiddleware` with configurable origins, methods, headers, and preflight support.
- Global middleware pipeline wired through `RouteDispatcher`.
- Configuration validation in `App` and clearer error messages.
- `CONTRIBUTING.md` and `CHANGELOG.md`.

### Changed

- `Config` and `Env` are now instance-based and owned by `App`, passed through DI.
- Route registration split into `RouteRegistrar` and `RouteDispatcher`.
- `Pipeline` restored and integrated into route dispatch.
- `CorsMiddleware` in gRPC renamed to `GrpcCorsMiddleware` to avoid confusion with the HTTP middleware.
- README updated to reflect the current code, configuration, and examples.

### Fixed

- `PredisCache::set` and `setMultiple` now correctly handle Predis `Status` responses.
- `Container` now resolves scalar/optional/union parameters correctly and fails with clearer messages.
- `Env` merge order fixed so project `.env` overrides framework `.env`.
- `.env` is now loaded early enough to override config values.
- `Config::get` no longer drops falsey environment values.
- HTTP server boot race fixed by replacing `OpenSwoole\Process::signal` with `pcntl_signal`.

### Removed

- Dead code, unused static singletons, and leftover debug echoes.
