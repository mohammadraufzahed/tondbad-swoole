<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Database\Migrations\Migrator;

class MigrateFreshCommand extends Command
{
    public function getName(): string
    {
        return 'migrate:fresh';
    }

    public function getDescription(): string
    {
        return 'Drop all tables and re-run all migrations.';
    }

    public function run(array $args): int
    {
        $migrator = $this->getMigrator();
        $migrations = $migrator->fresh();

        foreach ($migrations as $migration) {
            fwrite(STDOUT, "Migrated: {$migration}\n");
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
