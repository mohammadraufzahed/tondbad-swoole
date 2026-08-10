<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts;

use TondbadSwoole\Core\Container;

interface ServiceProviderInterface
{
    /**
     * Actions to perform before the registration of services.
     */
    public function beforeRegister(Container $container): void;

    /**
     * Register services or bindings in the container.
     */
    public function register(Container $container): void;

    /**
     * Actions to perform after the registration of services.
     */
    public function afterRegister(Container $container): void;

    /**
     * Actions to perform before booting the services.
     */
    public function beforeBoot(Container $container): void;

    /**
     * Boot the services that are registered in the container.
     */
    public function boot(Container $container): void;

    /**
     * Actions to perform after booting the services.
     */
    public function afterBoot(Container $container): void;

    /**
     * Providers with lower priority values are registered and booted first.
     */
    public function getPriority(): int;
}
