<?php

declare(strict_types=1);

use TondbadSwoole\Core\Route\RouteRegistrar;
use TondbadSwoole\Validation\Schema;

it('stores and validates route parameter schemas', function () {
    $registrar = new RouteRegistrar();
    $id = $registrar->addRoute('GET', '/users/{id}', fn () => null);
    $registrar->setSchema($id, 'id', Schema::int()->gte(1));

    $schema = $registrar->getSchema($id, 'id');

    expect($schema)->not->toBeNull();

    $valid = $schema->safeParse('5');
    $invalid = $schema->safeParse('abc');

    expect($valid->valid)->toBeTrue()
        ->and($valid->data)->toBe(5)
        ->and($invalid->valid)->toBeFalse();
});

it('updates route constraints so schema parameters match any segment', function () {
    $registrar = new RouteRegistrar();
    $id = $registrar->addRoute('GET', '/users/{id}', fn () => null);
    $registrar->setSchema($id, 'id', Schema::int()->gte(1));

    $info = $registrar->getDispatcher()->dispatch('GET', '/users/abc');

    expect($info[0])->toBe(\FastRoute\Dispatcher::FOUND)
        ->and($info[2])->toBe(['id' => 'abc']);
});
