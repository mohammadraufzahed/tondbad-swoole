<?php

namespace TondbadSwoole\Providers\Contracts;

use TondbadSwoole\Core\Container;

class ServiceProvider
{

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function beforeRegister(Container $container)
    {
    }

    public function afterRegister(Container $container)
    {
    }

    public function beforeBoot(Container $container)
    {
    }

    public function afterBoot(Container $container)
    {
    }
}