<?php

declare(strict_types=1);

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\PdoConnection;
use TondbadSwoole\Database\Query\Grammars\MySqlGrammar;
use TondbadSwoole\Database\Query\Grammars\SqliteGrammar;

it('resolves the default connection with mysql grammar', function () {
    $manager = new DatabaseManager($this->config);
    $connection = $manager->connection();

    expect($connection)->toBeInstanceOf(PdoConnection::class);
    expect($connection->getGrammar())->toBeInstanceOf(MySqlGrammar::class);
});

it('switches grammar based on configured driver', function () {
    $this->config->set('database.default', 'sqlite');
    $this->config->set('database.connections.sqlite.driver', 'sqlite');

    $manager = new DatabaseManager($this->config);

    expect($manager->connection()->getGrammar())->toBeInstanceOf(SqliteGrammar::class);
});

it('caches the same connection instance', function () {
    $manager = new DatabaseManager($this->config);

    expect($manager->connection())->toBe($manager->connection());
});

it('returns the configured default connection name', function () {
    $manager = new DatabaseManager($this->config);

    expect($manager->getDefaultConnection())->toBe('mysql');
});
