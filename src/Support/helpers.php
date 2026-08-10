<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;

if (!function_exists('app')) {
    function app(): ?App
    {
        return App::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()?->container->make(Config::class)->get($key, $default);
    }
}

if (!function_exists('cache')) {
    function cache(): ?CacheInterface
    {
        return app()?->container->make(CacheInterface::class);
    }
}
