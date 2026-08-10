<?php

declare(strict_types=1);

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
        $container->singleton(Logger::class, function () use ($container) {
            $config = $container->make(Config::class);

            $logger = new Logger($config->get('app.name', 'Tondbad Framework'));

            $logger->pushHandler(new StreamHandler('php://stdout'));
            $logger->pushHandler(new StreamHandler('php://stderr', Level::Error));

            $logPath = $config->get('app.logging.path');
            if ($logPath !== null) {
                $logDir = dirname($logPath);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0775, true);
                }

                $levelName = strtolower((string) $config->get('app.logging.level', 'info'));
                try {
                    $level = Level::fromName($levelName);
                } catch (\Throwable $e) {
                    $level = Level::Info;
                }

                $logger->pushHandler(new StreamHandler($logPath, $level));
            }

            return $logger;
        });
    }
}
