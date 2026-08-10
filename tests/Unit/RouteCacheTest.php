<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use TondbadSwoole\Core\Route\RouteRegistrar;

class RouteCacheTest extends TestCase
{
    public function test_compiles_route_cache_file(): void
    {
        $cacheFile = $this->cacheFile();

        $registrar = new RouteRegistrar($cacheFile);
        $registrar->addRoute('GET', '/hello', fn() => 'hello');
        $registrar->getDispatcher();

        $this->assertFileExists($cacheFile);
    }

    public function test_cached_routes_take_precedence_over_new_routes(): void
    {
        $cacheFile = $this->cacheFile();

        $first = new RouteRegistrar($cacheFile);
        $first->addRoute('GET', '/first', fn() => 'first');
        $first->getDispatcher();

        $second = new RouteRegistrar($cacheFile);
        $second->addRoute('GET', '/second', fn() => 'second');
        $dispatcher = $second->getDispatcher();

        $result = $dispatcher->dispatch('GET', '/first');
        $this->assertSame(\FastRoute\Dispatcher::FOUND, $result[0]);

        $missing = $dispatcher->dispatch('GET', '/second');
        $this->assertSame(\FastRoute\Dispatcher::NOT_FOUND, $missing[0]);
    }

    private function cacheFile(): string
    {
        return sys_get_temp_dir() . '/tondbad_route_cache_' . uniqid() . '.php';
    }
}
