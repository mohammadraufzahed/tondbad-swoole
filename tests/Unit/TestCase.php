<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Core\Route\Route;

abstract class TestCase extends BaseTestCase
{
    private array $originalEnv = [];
    private array $originalServer = [];

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->originalServer = $_SERVER;
        $_ENV = [];
        $_SERVER = [];

        parent::setUp();

        $this->resetStaticState();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();

        $_ENV = $this->originalEnv;
        $_SERVER = $this->originalServer;

        parent::tearDown();
    }

    private function resetStaticState(): void
    {
        $this->setStaticProperty(Config::class, 'config', []);
        $this->setStaticProperty(Config::class, 'loadedFiles', []);
        $this->setStaticProperty(Config::class, 'searchPaths', []);

        $this->setStaticProperty(Env::class, 'envCache', []);
        $this->setStaticProperty(Env::class, 'loadedFiles', []);

        $this->setStaticProperty(Container::class, 'instance', null);

        $this->setStaticProperty(Route::class, 'routes', []);
    }

    protected function setConfigSearchPaths(array $paths): void
    {
        $this->setStaticProperty(Config::class, 'searchPaths', $paths);
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($class);
        if (!$reflection->hasProperty($property)) {
            return;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
    }
}
