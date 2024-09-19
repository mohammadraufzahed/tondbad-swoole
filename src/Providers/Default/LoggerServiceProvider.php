<?php

namespace TondbadSwoole\Providers\Default;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Providers\Contracts\ServiceProviderInterface;

class LoggerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(Logger::class, function () {
            $logger = new Logger(
                Config::get('app.name', 'Tondbad Framework')
            );
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Debug));
            $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));
            $logger->pushHandler(new StreamHandler('php://stderr', Level::Error));
            return $logger;
        });
    }

    public function boot(Container $container): void
    {
    }
}