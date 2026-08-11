<?php

declare(strict_types=1);

namespace TondbadSwoole\Providers\Contracts;

use TondbadSwoole\Contracts\ServiceProviderInterface;
use TondbadSwoole\Core\Container;

abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @var list<string>
     */
    protected array $migrationPaths = [];

    /**
     * Register services or bindings in the container.
     * This method should be overridden in child classes to add custom services.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function register(Container $container): void
    {
    }

    /**
     * Boot the services that are registered in the container.
     * This method should be overridden in child classes to perform post-registration actions.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function boot(Container $container): void
    {
    }

    /**
     * Actions to perform before the registration of services.
     * This method can be overridden in child classes to define pre-registration logic.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function beforeRegister(Container $container): void
    {
    }

    /**
     * Actions to perform after the registration of services.
     * This method can be overridden in child classes to define post-registration logic.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function afterRegister(Container $container): void
    {
    }

    /**
     * Actions to perform before booting the services.
     * This method can be overridden in child classes to define pre-boot logic.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function beforeBoot(Container $container): void
    {
    }

    /**
     * Actions to perform after booting the services.
     * This method can be overridden in child classes to define post-boot logic.
     *
     * @param Container $container The application container instance.
     * @return void
     */
    public function afterBoot(Container $container): void
    {
    }

    /**
     * Register a directory that contains migration files.
     * Paths are collected by the database service provider during boot.
     */
    public function loadMigrationsFrom(string $path): self
    {
        $this->migrationPaths[] = $path;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMigrationPaths(): array
    {
        return $this->migrationPaths;
    }

    /**
     * Get the priority of the service provider.
     * Providers with lower priority values are registered and booted first.
     * This method can be overridden in child classes to specify custom priority values.
     *
     * @return int The priority value of the service provider. Default is 0.
     */
    public function getPriority(): int
    {
        return 0;
    }

}
