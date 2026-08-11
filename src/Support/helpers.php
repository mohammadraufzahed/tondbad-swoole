<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;

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

if (!function_exists('db')) {
    function db(?string $connection = null): ConnectionInterface|DatabaseManager|null
    {
        $manager = app()?->container->make(DatabaseManager::class);

        if ($manager === null) {
            return null;
        }

        return $connection !== null ? $manager->connection($connection) : $manager;
    }
}
