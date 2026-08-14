<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Support;

use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedGenericContainer;
use Testcontainers\Wait\WaitForLog;

class RedisContainer
{
    private static ?StartedGenericContainer $redis = null;

    public static function enabled(): bool
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            return false;
        }

        return extension_loaded('redis') || class_exists('Predis\\Client');
    }

    public static function start(): void
    {
        if (!self::enabled() || self::$redis !== null) {
            return;
        }

        self::$redis = (new GenericContainer('redis:7-alpine'))
            ->withExposedPorts(6379)
            ->withWait(new WaitForLog('Ready to accept connections'))
            ->start();
    }

    public static function stop(): void
    {
        self::$redis?->stop();
        self::$redis = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        if (self::$redis === null) {
            throw new \RuntimeException('Redis container has not been started.');
        }

        return [
            'scheme' => 'tcp',
            'host' => self::$redis->getHost(),
            'port' => self::$redis->getMappedPort(6379),
            'path' => null,
            'password' => null,
            'database' => 0,
            'timeout' => 5.0,
            'read_write_timeout' => null,
            'persistent' => false,
            'retry_interval' => 0,
            'options' => [
                'prefix' => 'tondbad_test_',
                'serializer' => 'php',
                'compression' => null,
            ],
        ];
    }
}
