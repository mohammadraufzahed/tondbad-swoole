<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

use RuntimeException;
use Throwable;
use TondbadSwoole\Database\ConnectionInterface;

class Migrator
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly ConnectionInterface $connection,
        private readonly MigrationPathManager $paths,
    ) {
    }

    public function run(array $options = []): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();
        $migrations = array_diff($files, $ran);

        $this->requireFiles($migrations);
        $this->runPending($migrations, $options);

        return $migrations;
    }

    public function runPending(array $migrations, array $options = []): void
    {
        if (empty($migrations)) {
            return;
        }

        $batch = $this->repository->getNextBatchNumber();

        foreach ($migrations as $file) {
            $this->runUp($file, $batch);
        }
    }

    public function rollback(?int $steps = null, bool $pretend = false): array
    {
        $migrations = $this->repository->getLast();

        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }

        $this->requireFiles($migrations);

        foreach ($migrations as $migration) {
            $this->runDown($migration);
        }

        return $migrations;
    }

    public function reset(): array
    {
        $migrations = $this->repository->getRan();
        $migrations = array_reverse($migrations);

        $this->requireFiles($migrations);

        foreach ($migrations as $migration) {
            $this->runDown($migration);
        }

        return $migrations;
    }

    public function fresh(): array
    {
        $this->repository->deleteRepository();
        $this->repository->createRepository();

        return $this->run();
    }

    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    public function getPathManager(): MigrationPathManager
    {
        return $this->paths;
    }

    /**
     * @return list<string>
     */
    public function getMigrationFiles(): array
    {
        return $this->paths->getFiles();
    }

    protected function runUp(string $file, int $batch): void
    {
        $instance = $this->resolve($file);

        $this->runMigration($instance, 'up');
        $this->repository->log($file, $batch);
    }

    protected function runDown(string $file): void
    {
        $instance = $this->resolve($file);

        $this->runMigration($instance, 'down');
        $this->repository->delete($file);
    }

    protected function runMigration(Migration $migration, string $method): void
    {
        try {
            $migration->$method();
        } catch (Throwable $e) {
            throw new RuntimeException("Migration failed [{$method}]: " . $e->getMessage(), 0, $e);
        }
    }

    protected function resolve(string $file): Migration
    {
        $path = $this->paths->getFullPath($file);

        if ($path === null) {
            throw new RuntimeException("Migration file not found: {$file}");
        }

        require_once $path;

        $className = $this->getClassName($file);

        if (!class_exists($className)) {
            throw new RuntimeException("Migration class {$className} not found in {$path}");
        }

        return new $className();
    }

    protected function getClassName(string $file): string
    {
        $base = basename($file, '.php');

        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.*)$/', $base, $matches)) {
            $base = $matches[1];
        }

        $base = preg_replace('/[^A-Za-z0-9_]+/', '_', $base) ?? $base;
        $base = trim($base, '_');

        return implode('', array_map('ucfirst', explode('_', $base)));
    }

    protected function requireFiles(array $files): void
    {
        foreach ($files as $file) {
            $path = $this->paths->getFullPath($file);

            if ($path !== null) {
                require_once $path;
            }
        }
    }
}
