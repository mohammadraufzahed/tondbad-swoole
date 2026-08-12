<?php

declare(strict_types=1);

use TondbadSwoole\Core\Config;
use TondbadSwoole\Core\Env;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Tests\Support\DatabaseContainer;

beforeAll(function () {
    DatabaseContainer::startMysql();
    DatabaseContainer::startPostgres();
});

afterAll(function () {
    DatabaseContainer::stop();
});

it('connects to mysql via testcontainers and runs a query', function () {
    $config = new Config(new Env());
    $config->set('database.default', 'mysql');
    $config->set('database.connections.mysql', DatabaseContainer::mysqlConfig());
    $manager = new DatabaseManager($config);

    $result = $manager->select('SELECT 1 as one');

    expect($result)->toBe([['one' => 1]]);
})->skip(fn () => !DatabaseContainer::enabled('mysql'), 'Integration tests disabled; set RUN_INTEGRATION_TESTS=1 and ensure pdo_mysql is loaded');

it('connects to postgresql via testcontainers and runs a query', function () {
    $config = new Config(new Env());
    $config->set('database.default', 'pgsql');
    $config->set('database.connections.pgsql', DatabaseContainer::postgresConfig());
    $manager = new DatabaseManager($config);

    $result = $manager->select('SELECT 1 as one');

    expect($result)->toBe([['one' => 1]]);
})->skip(fn () => !DatabaseContainer::enabled('pgsql'), 'Integration tests disabled; set RUN_INTEGRATION_TESTS=1 and ensure pdo_pgsql is loaded');
