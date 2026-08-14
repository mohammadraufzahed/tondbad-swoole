<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\GuardFactory;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Auth\Guards\ApiKeyGuard;
use TondbadSwoole\Auth\Guards\AccessTokenGuard;
use TondbadSwoole\Auth\Guards\BasicAuthGuard;
use TondbadSwoole\Auth\Guards\SessionGuard;
use TondbadSwoole\Auth\Guards\TokenGuard;
use TondbadSwoole\Auth\Session\AccessToken;
use TondbadSwoole\Auth\Session\AuthSession;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Auth\SessionStores\DatabaseSessionStore;
use TondbadSwoole\Auth\Strategies\ApiKeyStrategy;
use TondbadSwoole\Auth\Strategies\AuthStrategy;
use TondbadSwoole\Auth\Strategies\EmailPasswordStrategy;
use TondbadSwoole\Auth\UserProviders\ApiKeyUserProvider;
use TondbadSwoole\Auth\UserProviders\DatabaseUserProvider;
use TondbadSwoole\Auth\UserProviders\EloquentUserProvider;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Http\Request;
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

    private ?AuthUserManager $userManager = null;

    /**
     * @var array<string, AuthStrategy>
     */
    private array $strategies = [];

    /**
     * @var array<string, Closure(UserProvider, AuthUserManager): AuthStrategy>
     */
    private array $strategyFactories = [];

    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
        private readonly ContextInterface $context,
        private ?SessionManager $sessionManager = null,
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
        $this->context->set('request', $request);

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

    public function session(?string $guard = null): ?Session
    {
        $guardName = $guard ?? $this->getDefaultGuard();

        return $this->context->get('auth.guard.' . $guardName . '.session');
    }

    public function login(Authenticatable $user, ?string $guard = null, array $claims = []): AuthSession
    {
        $guard = $this->guard($guard);

        if (!$guard instanceof \TondbadSwoole\Auth\Contracts\StatefulGuard) {
            throw new InvalidArgumentException('Guard does not support login.');
        }

        return $guard->login($user, $claims);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function signIn(string $strategy, array $credentials = [], ?string $guard = null): ?AuthSession
    {
        $user = $this->strategy($strategy, $guard)->authenticate($credentials);

        if ($user === null) {
            return null;
        }

        return $this->login($user, $guard);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function signUp(string $strategy, array $data = [], ?string $guard = null): ?AuthSession
    {
        $user = $this->strategy($strategy, $guard)->register($data);

        if ($user === null) {
            return null;
        }

        return $this->login($user, $guard);
    }

    public function signOut(?string $guard = null): void
    {
        $this->logout($guard);
    }

    public function refreshSession(string $refreshToken, ?string $guard = null): ?AuthSession
    {
        return $this->sessionManager()->refreshSession($refreshToken);
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issueApiToken(string|int $userId, array $claims = [], ?int $ttl = null): AccessToken
    {
        $session = $this->sessionManager()->create($userId, $claims, 'stateless');

        return $session->accessToken;
    }

    public function revokeSession(string $sessionId): void
    {
        $this->sessionManager()->revokeSession($sessionId);
    }

    /**
     * @param string|int $userId
     */
    public function revokeAllSessions(string|int $userId): void
    {
        $this->sessionManager()->revokeAllForUser($userId);
    }

    /**
     * @param Closure(UserProvider, AuthUserManager): AuthStrategy $factory
     */
    public function addStrategy(string $name, Closure $factory): self
    {
        $this->strategyFactories[$name] = $factory;

        return $this;
    }

    public function strategy(string $name, ?string $guard = null): AuthStrategy
    {
        if (isset($this->strategies[$name])) {
            return $this->strategies[$name];
        }

        $factory = $this->strategyFactories[$name] ?? $this->defaultStrategyFactory($name);
        $provider = $this->resolveProviderForGuard($guard);

        return $this->strategies[$name] = $factory($provider, $this->userManager());
    }

    private function userManager(): AuthUserManager
    {
        if ($this->userManager !== null) {
            return $this->userManager;
        }

        return $this->userManager = $this->container->make(AuthUserManager::class);
    }

    /**
     * @return Closure(UserProvider, AuthUserManager): AuthStrategy
     */
    private function defaultStrategyFactory(string $name): Closure
    {
        return match ($name) {
            'email' => fn (UserProvider $provider, AuthUserManager $manager): AuthStrategy => new EmailPasswordStrategy('email', $provider, $manager),
            'api_key' => fn (UserProvider $provider, AuthUserManager $manager): AuthStrategy => new ApiKeyStrategy('api_key', $provider),
            default => throw new InvalidArgumentException("Auth strategy [{$name}] is not registered."),
        };
    }

    private function resolveProviderForGuard(?string $guard): UserProvider
    {
        $guardName = $guard ?? $this->getDefaultGuard();
        $guardConfig = $this->config->get("auth.guards.{$guardName}");

        if (!is_array($guardConfig)) {
            throw new InvalidArgumentException("Auth guard [{$guardName}] is not defined.");
        }

        return $this->resolveProvider($guardConfig['provider'] ?? null);
    }

    public function logout(?string $guard = null): void
    {
        $guard = $this->guard($guard);

        if ($guard instanceof \TondbadSwoole\Auth\Contracts\StatefulGuard) {
            $guard->logout();
        }
    }

    public function setSessionManager(SessionManager $sessionManager): self
    {
        $this->sessionManager = $sessionManager;

        return $this;
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
                $this->sessionManager(),
                $this->context,
                $this->config,
            ),
            'access_token' => new AccessTokenGuard(
                $name,
                $provider,
                $this->sessionManager(),
                $this->context,
                $this->config,
            ),
            'token' => new TokenGuard(
                $name,
                $provider,
                $this->context,
                $config['storage_key'] ?? 'api_token',
            ),
            'api_key' => new ApiKeyGuard(
                $name,
                $provider,
                $this->context,
                $config['storage_key'] ?? 'api_key',
            ),
            'basic' => new BasicAuthGuard(
                $name,
                $provider,
                $this->context,
                $config['username_key'] ?? 'email',
            ),
            default => throw new InvalidArgumentException("Auth driver [{$driver}] is not supported."),
        };
    }

    public function sessionManager(): SessionManager
    {
        if ($this->sessionManager !== null) {
            return $this->sessionManager;
        }

        $store = new DatabaseSessionStore($this->container->make(DatabaseManager::class));
        $accessTokenManager = new AccessTokenManager($this->config);
        $refreshTokenRepository = new RefreshTokenRepository(
            $this->container->make(DatabaseManager::class),
            $this->config,
        );

        return $this->sessionManager = new SessionManager(
            $this->config,
            $accessTokenManager,
            $refreshTokenRepository,
            $store,
        );
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
            'api_keys' => new ApiKeyUserProvider(
                $this->container->make(DatabaseManager::class),
                $config['users_table'] ?? 'users',
                $config['api_keys_table'] ?? 'api_keys',
                $config['key_column'] ?? 'key',
                $config['user_id_column'] ?? 'user_id',
                $config['expires_at_column'] ?? null,
                $config['auth_identifier'] ?? 'id',
                $config['auth_password'] ?? 'password',
            ),
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
