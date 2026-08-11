<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Migrations;

use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;

class MigrationRepository
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'migrations',
    ) {
        $this->createRepository();
    }

    public function getRan(): array
    {
        return $this->connection
            ->table($this->table)
            ->orderBy('batch', 'asc')
            ->orderBy('migration', 'asc')
            ->pluck('migration');
    }

    public function getMigrations(int $steps): array
    {
        return $this->connection
            ->table($this->table)
            ->where('batch', '>=', 1)
            ->orderBy('migration', 'desc')
            ->limit($steps)
            ->pluck('migration');
    }

    public function getLast(): array
    {
        $lastBatch = $this->getLastBatchNumber();

        return $this->connection
            ->table($this->table)
            ->where('batch', $lastBatch)
            ->orderBy('migration', 'desc')
            ->pluck('migration');
    }

    public function getMigrationBatches(): array
    {
        return $this->connection
            ->table($this->table)
            ->orderBy('batch', 'asc')
            ->orderBy('migration', 'asc')
            ->pluck('batch', 'migration');
    }

    public function log(string $file, int $batch): void
    {
        $this->connection->table($this->table)->insert([
            'migration' => $file,
            'batch' => $batch,
        ]);
    }

    public function delete(string $file): void
    {
        $this->connection->table($this->table)->where('migration', $file)->delete();
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function getLastBatchNumber(): int
    {
        $result = $this->connection
            ->table($this->table)
            ->max('batch');

        return (int) ($result ?? 0);
    }

    public function createRepository(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        if ($schema->hasTable($this->table)) {
            return;
        }

        $schema->create($this->table, function ($table): void {
            $table->id();
            $table->string('migration');
            $table->integer('batch');
            $table->index(['migration']);
            $table->index(['batch']);
        });
    }

    public function deleteRepository(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        if ($schema->hasTable($this->table)) {
            $schema->drop($this->table);
        }
    }
}
