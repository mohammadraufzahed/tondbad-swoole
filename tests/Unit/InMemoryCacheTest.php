<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use OpenSwoole\Timer;
use TondbadSwoole\Core\Cache\InMemoryCache;

class InMemoryCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        if (class_exists(Timer::class) && method_exists(Timer::class, 'clearAll')) {
            Timer::clearAll();
        }

        parent::tearDown();
    }

    public function test_set_and_get(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $this->assertTrue($cache->set('key', 'value'));
        $this->assertSame('value', $cache->get('key'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $this->assertNull($cache->get('missing'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $cache->set('key', 'value');

        $this->assertTrue($cache->has('key'));
    }

    public function test_delete_removes_key(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $cache->set('key', 'value');
        $this->assertTrue($cache->delete('key'));

        $this->assertNull($cache->get('key'));
        $this->assertFalse($cache->has('key'));
    }

    public function test_ttl_expires_item(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $cache->set('key', 'value', 1);
        $this->assertTrue($cache->has('key'));

        sleep(2);

        $this->assertNull($cache->get('key'));
        $this->assertFalse($cache->has('key'));
    }

    public function test_clear_removes_all_keys(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $cache->set('a', 1);
        $cache->set('b', 2);

        $this->assertTrue($cache->clear());

        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function test_set_multiple_and_get_multiple(): void
    {
        $cache = new InMemoryCache(1024, 3600000);

        $this->assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));

        $this->assertSame(['a' => 1, 'b' => 2], $cache->getMultiple(['a', 'b']));
    }
}
