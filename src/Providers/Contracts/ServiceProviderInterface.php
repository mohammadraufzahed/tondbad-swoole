<?php

namespace TondbadSwoole\Providers\Contracts;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Traits\ServiceProviderEventsTrait;

interface ServiceProviderInterface
{
    use ServiceProviderEventsTrait;

    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }
}