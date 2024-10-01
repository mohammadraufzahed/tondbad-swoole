<?php
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class EnvServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        Env::loadAll();
    }

    public function getPriority(): int
    {
        return 2;
    }
}