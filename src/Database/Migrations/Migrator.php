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
        private readonly string $path,
    ) {
    }

    public function run(?string $path = null, array $options = []): array
    {
        $path ??= $this->path;
        $files = $this->getMigrationFiles($path);
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

    public function fresh(?string $path = null): array
    {
        $this->repository->deleteRepository();
        $this->repository->createRepository();

        return $this->run($path);
    }

    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    public function getMigrationFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*_*.php');

        if ($files === false) {
            return [];
        }

        sort($files);

        return array_map(fn (string $file): string => basename($file), $files);
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
        $path = $this->path . '/' . $file;

        if (!file_exists($path)) {
            throw new RuntimeException("Migration file not found: {$path}");
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
            $path = $this->path . '/' . $file;

            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
