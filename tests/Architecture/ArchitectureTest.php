<?php

declare(strict_types=1);

arch('contracts are independent of concrete implementations')
    ->expect('TondbadSwoole\Contracts')
    ->not->toUse([
        'TondbadSwoole\Core',
        'TondbadSwoole\Database',
        'TondbadSwoole\Providers',
        'TondbadSwoole\Queue',
        'TondbadSwoole\Scheduling',
        'TondbadSwoole\Console',
    ])
    ->ignoring([
        'TondbadSwoole\Contracts\ServiceProviderInterface',
        'TondbadSwoole\Http',
    ]);

arch('strict types are used across the framework')
    ->expect('TondbadSwoole')
    ->toUseStrictTypes();

arch('no debug helpers in source')
    ->expect('TondbadSwoole')
    ->not->toUse(['dd', 'dump', 'die', 'var_dump', 'exit']);

arch('database grammar delegates dialect to operations and features')
    ->expect('TondbadSwoole\Database\Query\Grammar')
    ->toUse([
        'TondbadSwoole\Database\Contracts\DatabaseOperations',
        'TondbadSwoole\Database\Contracts\DatabaseFeatures',
    ]);

arch('database engines implement the engine contract')
    ->expect('TondbadSwoole\Database\Engines')
    ->toImplement('TondbadSwoole\Database\Engines\Contracts\DatabaseEngine')
    ->ignoring([
        'TondbadSwoole\Database\Engines\EngineFactory',
        'TondbadSwoole\Database\Engines\Contracts',
        'TondbadSwoole\Database\Engines\AbstractDatabaseEngine',
    ]);

arch('database wrapper implements the connection interface')
    ->expect('TondbadSwoole\Database\DatabaseWrapper')
    ->toImplement('TondbadSwoole\Database\ConnectionInterface');
