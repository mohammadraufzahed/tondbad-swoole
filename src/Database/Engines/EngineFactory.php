<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Engines;

use RuntimeException;
use TondbadSwoole\Database\Engines\Contracts\DatabaseEngine;

class EngineFactory
{
    /**
     * @var array<string, class-string<DatabaseEngine>>
     */
    private array $engines = [
        'mysql' => MySqlEngine::class,
        'pgsql' => PostgresEngine::class,
        'postgres' => PostgresEngine::class,
        'postgresql' => PostgresEngine::class,
        'sqlite' => SqliteEngine::class,
    ];

    /**
     * @param class-string<DatabaseEngine> $engineClass
     */
    public function extend(string $driver, string $engineClass): void
    {
        $this->engines[$driver] = $engineClass;
    }

    public function make(string $driver): DatabaseEngine
    {
        $class = $this->engines[$driver] ?? null;

        if ($class === null) {
            throw new RuntimeException("Unsupported database driver: {$driver}");
        }

        $engine = new $class();

        if (!$engine instanceof DatabaseEngine) {
            throw new RuntimeException("Database engine [{$class}] must implement DatabaseEngine.");
        }

        return $engine;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function makeForConfig(array $config): DatabaseEngine
    {
        $driver = $config['driver'] ?? 'mysql';

        return $this->make($driver);
    }
}
