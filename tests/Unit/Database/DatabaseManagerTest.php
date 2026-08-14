<?php

declare(strict_types=1);

use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Database\DatabaseWrapper;
use TondbadSwoole\Database\Query\Grammars\MySqlGrammar;
use TondbadSwoole\Database\Query\Grammars\SqliteGrammar;

it('resolves the default connection with the configured driver grammar', function () {
    $manager = new DatabaseManager($this->config);
    $connection = $manager->connection();
    $default = $manager->getDefaultConnection();

    $grammarMap = [
        'mysql' => MySqlGrammar::class,
        'sqlite' => SqliteGrammar::class,
        'pgsql' => \TondbadSwoole\Database\Query\Grammars\PostgresGrammar::class,
    ];

    expect($connection)->toBeInstanceOf(DatabaseWrapper::class);
    expect($connection->getGrammar())->toBeInstanceOf($grammarMap[$default] ?? MySqlGrammar::class);
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

    expect($manager->getDefaultConnection())->toBe($this->config->get('database.default'));
});
