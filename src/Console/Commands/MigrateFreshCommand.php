<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\Migrations\Migrator;

#[AsCommand('migrate:fresh', 'Drop all tables and re-run all migrations.')]
class MigrateFreshCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->getMigrator();
        $migrations = $migrator->fresh();

        foreach ($migrations as $migration) {
            $output->success("Migrated: {$migration}");
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
