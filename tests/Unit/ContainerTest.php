<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit;

use Exception;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Tests\Unit\Fixtures\Logger;
use TondbadSwoole\Tests\Unit\Fixtures\Repository;
use TondbadSwoole\Tests\Unit\Fixtures\RequiredScalar;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithClassDependency;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithNoConstructor;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithOptional;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithUnion;

class ContainerTest extends TestCase
{
    public function test_resolve_class_without_constructor(): void
    {
        $container = Container::create();

        $service = $container->make(ServiceWithNoConstructor::class);

        $this->assertInstanceOf(ServiceWithNoConstructor::class, $service);
    }

    public function test_resolve_class_with_class_dependency(): void
    {
        $container = Container::create();

        $service = $container->make(ServiceWithClassDependency::class);

        $this->assertInstanceOf(ServiceWithClassDependency::class, $service);
        $this->assertInstanceOf(Repository::class, $service->repository);
    }

    public function test_resolve_class_with_optional_dependency_resolves_when_possible(): void
    {
        $container = Container::create();

        $service = $container->make(ServiceWithOptional::class);

        $this->assertInstanceOf(ServiceWithOptional::class, $service);
        $this->assertInstanceOf(Logger::class, $service->logger);
    }

    public function test_resolve_class_with_union_dependency(): void
    {
        $container = Container::create();

        $service = $container->make(ServiceWithUnion::class);

        $this->assertInstanceOf(ServiceWithUnion::class, $service);
        $this->assertInstanceOf(Logger::class, $service->logger);
    }

    public function test_singleton_returns_same_instance(): void
    {
        $container = Container::create();

        $container->singleton(Logger::class, fn() => new Logger('singleton'));

        $first = $container->make(Logger::class);
        $second = $container->make(Logger::class);

        $this->assertSame($first, $second);
    }

    public function test_bind_class_string_resolves_instance(): void
    {
        $container = Container::create();

        $container->bind('repository', Repository::class);

        $this->assertInstanceOf(Repository::class, $container->make('repository'));
    }

    public function test_bind_scalar_value_returns_value(): void
    {
        $container = Container::create();

        $container->bind('app.name', 'Tondbad Test');

        $this->assertSame('Tondbad Test', $container->make('app.name'));
    }

    public function test_resolve_unresolvable_scalar_parameter_throws(): void
    {
        $this->expectException(Exception::class);

        $container = Container::create();
        $container->make(RequiredScalar::class);
    }

    public function test_has_reports_existing_binding(): void
    {
        $container = Container::create();

        $container->bind('foo', 'bar');

        $this->assertTrue($container->has('foo'));
        $this->assertFalse($container->has('baz'));
    }
}
