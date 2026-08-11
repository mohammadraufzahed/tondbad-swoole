<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Env;

class TestCase extends BaseTestCase
{
    protected Env $env;
    protected Config $config;

    private array $originalEnv = [];
    private array $originalServer = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;
        $_ENV = [];
        $_SERVER = [];

        $this->env = new Env();
        $this->config = new Config($this->env);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;
        $_SERVER = $this->originalServer;

        if (class_exists(\OpenSwoole\Timer::class) && method_exists(\OpenSwoole\Timer::class, 'clearAll')) {
            \OpenSwoole\Timer::clearAll();
        }

        parent::tearDown();
    }

    protected function setConfigSearchPaths(array $paths): void
    {
        $this->config->setSearchPaths($paths);
    }

    protected function tempDir(string $prefix = 'tondbad_test'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . '_' . uniqid();
        mkdir($dir, 0777, true);

        return $dir;
    }
}
