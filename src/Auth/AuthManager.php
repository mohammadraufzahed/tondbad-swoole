<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\GuardFactory;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Auth\Guards\ApiKeyGuard;
use TondbadSwoole\Auth\Guards\BasicAuthGuard;
use TondbadSwoole\Auth\Guards\SessionGuard;
use TondbadSwoole\Auth\Guards\TokenGuard;
use TondbadSwoole\Auth\UserProviders\DatabaseUserProvider;
use TondbadSwoole\Auth\UserProviders\EloquentUserProvider;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Support\Context;
use Closure;
use InvalidArgumentException;

class AuthManager
{
    /**
     * @var array<string, Guard>
     */
    private array $guards = [];

    /**
     * @var array<string, GuardFactory|Closure>
     */
    private array $customGuardFactories = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {
    }

    /**
     * Register a custom guard.
     *
     * Accepts a closure `fn(Container, UserProvider, array, string): Guard` or a
     * class-string implementing `GuardFactory`.
     *
     * @param Closure(Container, UserProvider, array<string, mixed>, string): Guard|class-string<GuardFactory> $factory
     */
    public function extend(string $name, Closure|string $factory): self
    {
        if (is_string($factory) && (!class_exists($factory) || !is_subclass_of($factory, GuardFactory::class))) {
            throw new InvalidArgumentException("Custom guard [{$name}] must be a closure or a class implementing [" . GuardFactory::class . '].');
        }

        $this->customGuardFactories[$name] = $factory;

        return $this;
    }

    public function guard(?string $name = null): Guard
    {
        $name = $name ?? $this->getDefaultGuard();

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        return $this->guards[$name] = $this->resolveGuard($name);
    }

    public function setRequest(Request $request): self
    {
        Context::set('request', $request);

        return $this;
    }

    public function check(?string $guard = null): bool
    {
        return $this->guard($guard)->check();
    }

    public function guest(?string $guard = null): bool
    {
        return $this->guard($guard)->guest();
    }

    public function user(?string $guard = null): ?Authenticatable
    {
        return $this->guard($guard)->user();
    }

    public function id(?string $guard = null): string|int|null
    {
        return $this->guard($guard)->id();
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials = [], ?string $guard = null): bool
    {
        return $this->guard($guard)->validate($credentials);
    }

    public function getDefaultGuard(): string
    {
        return (string) $this->config->get('auth.defaults.guard', 'token');
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->{$method}(...$parameters);
    }

    private function resolveGuard(string $name): Guard
    {
        $config = $this->config->get("auth.guards.{$name}");

        if (!is_array($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driver = $config['driver'] ?? 'token';
        $provider = $this->resolveProvider($config['provider'] ?? null);

        if (!isset($this->customGuardFactories[$driver]) && is_string($driver) && class_exists($driver) && is_subclass_of($driver, GuardFactory::class)) {
            $this->customGuardFactories[$driver] = $driver;
        }

        if (isset($this->customGuardFactories[$driver])) {
            $factory = $this->customGuardFactories[$driver];

            if ($factory instanceof GuardFactory) {
                return $factory->create($this->container, $provider, $config, $name);
            }

            if (is_string($factory)) {
                $factoryInstance = $this->container->make($factory);

                return $factoryInstance->create($this->container, $provider, $config, $name);
            }

            return $factory($this->container, $provider, $config, $name);
        }

        return match ($driver) {
            'session' => new SessionGuard(
                $name,
                $provider,
                $this->container->make(CacheInterface::class),
                $config['session_key'] ?? 'session_id',
                $config['lifetime'] ?? 7200,
            ),
            'token' => new TokenGuard(
                $name,
                $provider,
                $config['storage_key'] ?? 'api_token',
            ),
            'api_key' => new ApiKeyGuard(
                $name,
                $provider,
                $config['storage_key'] ?? 'api_key',
            ),
            'basic' => new BasicAuthGuard(
                $name,
                $provider,
                $config['username_key'] ?? 'email',
            ),
            default => throw new InvalidArgumentException("Auth driver [{$driver}] is not supported."),
        };
    }

    private function resolveProvider(?string $name): UserProvider
    {
        $name = $name ?? $this->config->get('auth.defaults.provider');

        if (!is_string($name)) {
            throw new InvalidArgumentException('Auth provider is not defined.');
        }

        $config = $this->config->get("auth.providers.{$name}");

        if (!is_array($config)) {
            throw new InvalidArgumentException("Auth provider [{$name}] is not defined.");
        }

        $driver = $config['driver'] ?? 'database';

        return match ($driver) {
            'eloquent' => new EloquentUserProvider($config['model'] ?? ''),
            'database' => new DatabaseUserProvider(
                $this->container->make(DatabaseManager::class),
                $config['table'] ?? 'users',
                $config['auth_identifier'] ?? 'id',
                $config['auth_password'] ?? 'password',
            ),
            default => throw new InvalidArgumentException("Auth provider driver [{$driver}] is not supported."),
        };
    }
}
