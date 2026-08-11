<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Tests\Unit\Fixtures\Logger;
use TondbadSwoole\Tests\Unit\Fixtures\Repository;
use TondbadSwoole\Tests\Unit\Fixtures\RequiredScalar;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithClassDependency;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithNoConstructor;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithOptional;
use TondbadSwoole\Tests\Unit\Fixtures\ServiceWithUnion;

it('resolves a class without a constructor', function () {
    $container = new Container();
    $service = $container->make(ServiceWithNoConstructor::class);

    expect($service)->toBeInstanceOf(ServiceWithNoConstructor::class);
});

it('resolves a class with a class dependency', function () {
    $container = new Container();
    $service = $container->make(ServiceWithClassDependency::class);

    expect($service)->toBeInstanceOf(ServiceWithClassDependency::class);
    expect($service->repository)->toBeInstanceOf(Repository::class);
});

it('resolves a class with an optional dependency when possible', function () {
    $container = new Container();
    $service = $container->make(ServiceWithOptional::class);

    expect($service)->toBeInstanceOf(ServiceWithOptional::class);
    expect($service->logger)->toBeInstanceOf(Logger::class);
});

it('resolves a class with a union dependency', function () {
    $container = new Container();
    $service = $container->make(ServiceWithUnion::class);

    expect($service)->toBeInstanceOf(ServiceWithUnion::class);
    expect($service->logger)->toBeInstanceOf(Logger::class);
});

it('returns the same instance for singletons', function () {
    $container = new Container();
    $container->singleton(Logger::class, fn () => new Logger('singleton'));

    $first = $container->make(Logger::class);
    $second = $container->make(Logger::class);

    expect($first)->toBe($second);
});

it('resolves a bound class string', function () {
    $container = new Container();
    $container->bind('repository', Repository::class);

    expect($container->make('repository'))->toBeInstanceOf(Repository::class);
});

it('returns a bound scalar value', function () {
    $container = new Container();
    $container->bind('app.name', 'Tondbad Test');

    expect($container->make('app.name'))->toBe('Tondbad Test');
});

it('throws when a scalar parameter cannot be resolved', function () {
    $container = new Container();
    $container->make(RequiredScalar::class);
})->throws(Exception::class);

it('reports existing bindings through has()', function () {
    $container = new Container();
    $container->bind('foo', 'bar');

    expect($container->has('foo'))->toBeTrue();
    expect($container->has('baz'))->toBeFalse();
});
