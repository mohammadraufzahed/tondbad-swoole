<?php

namespace TondbadSwoole\Providers\Default;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(Logger::class, function () {
            $logger = new Logger(
                Config::get('app.name', 'Tondbad Framework')
            );
            $logger->pushHandler(new StreamHandler('php://stdout'));
            $logger->pushHandler(new StreamHandler('php://stderr', Level::Error));
            return $logger;
        });
    }
}