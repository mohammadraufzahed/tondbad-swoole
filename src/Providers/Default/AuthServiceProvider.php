<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Default;

use TondbadSwoole\Auth\AccessTokenManager;
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\AuthUserManager;
use TondbadSwoole\Auth\Contracts\Guard;
use TondbadSwoole\Auth\Contracts\SessionStore;
use TondbadSwoole\Auth\Identity\HttpClient;
use TondbadSwoole\Auth\Identity\OpenSwooleHttpClient;
use TondbadSwoole\Auth\Mfa\EmailOtpFactor;
use TondbadSwoole\Auth\Mfa\MfaManager;
use TondbadSwoole\Auth\Mfa\TotpFactor;
use TondbadSwoole\Auth\RefreshTokenRepository;
use TondbadSwoole\Auth\SessionManager;
use TondbadSwoole\Auth\SessionStores\DatabaseSessionStore;
use TondbadSwoole\Auth\SessionStores\RedisSessionStore;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Core\Cache\RedisCache;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../Database/Migrations/Auth');

        $container->singleton(SessionStore::class, function () use ($container): SessionStore {
            $config = $container->make(Config::class);
            $store = (string) $config->get('auth.session.store', 'database');

            if ($store === 'redis') {
                return new RedisSessionStore($container->make(RedisCache::class));
            }

            return new DatabaseSessionStore($container->make(DatabaseManager::class));
        });

        $container->singleton(SessionManager::class, function () use ($container): SessionManager {
            $config = $container->make(Config::class);

            return new SessionManager(
                $config,
                new AccessTokenManager($config),
                new RefreshTokenRepository(
                    $container->make(DatabaseManager::class),
                    $config,
                ),
                $container->make(SessionStore::class),
            );
        });

        $container->bind(HttpClient::class, fn (): HttpClient => new OpenSwooleHttpClient());

        $container->singleton(AuthUserManager::class, function () use ($container): AuthUserManager {
            return new AuthUserManager(
                $container->make(DatabaseManager::class),
                $container->make(Config::class),
                $container->make(\TondbadSwoole\Support\Hash\Contracts\Hasher::class),
            );
        });

        $container->singleton(AuthManager::class, function () use ($container): AuthManager {
            return new AuthManager(
                $container,
                $container->make(Config::class),
                $container->make(ContextInterface::class),
                $container->make(SessionManager::class),
            );
        });

        $container->bind(Guard::class, function () use ($container): Guard {
            return $container->make(AuthManager::class)->guard();
        });

        $container->singleton(MfaManager::class, function () use ($container): MfaManager {
            $manager = new MfaManager(
                $container->make(DatabaseManager::class),
                $container->make(Config::class),
                $container->make(AuthManager::class),
            );

            $manager->registerFactor(new TotpFactor(
                $container->make(Config::class),
                $container->make(DatabaseManager::class),
            ));

            $manager->registerFactor(new EmailOtpFactor(
                $container->make(DatabaseManager::class),
            ));

            return $manager;
        });
    }
}
