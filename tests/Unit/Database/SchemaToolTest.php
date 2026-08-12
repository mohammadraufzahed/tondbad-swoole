<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\SchemaTool;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Product;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');
    schema()->dropIfExists('products');
});

afterEach(function () {
    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('generates create schema sql from entity attributes', function () {
    $tool = new SchemaTool(schema());
    $sql = $tool->getCreateSchemaSql([Product::class]);

    $combined = implode("\n", $sql);

    expect($combined)->toContain('create table');
    expect($combined)->toContain('products');
    expect($combined)->toContain('name');
    expect($combined)->toContain('price');
    expect($combined)->toContain('metadata');
    expect($combined)->toContain('active');
});

it('creates a schema from entity attributes', function () {
    $tool = new SchemaTool(schema());

    $tool->createSchema([Product::class]);

    expect(schema()->hasTable('products'))->toBeTrue();
    expect(schema()->hasColumn('products', 'name'))->toBeTrue();
    expect(schema()->hasColumn('products', 'price'))->toBeTrue();
    expect(schema()->hasColumn('products', 'metadata'))->toBeTrue();
    expect(schema()->hasColumn('products', 'active'))->toBeTrue();
});

it('generates update schema sql for missing columns', function () {
    schema()->create('products', function ($table) {
        $table->id();
        $table->string('name');
    });

    $tool = new SchemaTool(schema());
    $sql = $tool->getUpdateSchemaSql([Product::class]);

    $combined = implode("\n", $sql);

    expect($combined)->toContain('add column');
});

it('drops a schema for entity classes', function () {
    $tool = new SchemaTool(schema());

    $tool->createSchema([Product::class]);
    expect(schema()->hasTable('products'))->toBeTrue();

    $tool->dropSchema([Product::class]);
    expect(schema()->hasTable('products'))->toBeFalse();
});
