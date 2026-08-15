<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\Migrations\MigrationRepository;
use TondbadSwoole\Database\Migrations\Migrator;

#[AsCommand('migrate:status', 'Show the status of each migration.')]
class MigrateStatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->getMigrator();
        $files = $migrator->getMigrationFiles();
        $ran = app()->container->make(MigrationRepository::class)->getRan();

        $rows = [];

        foreach ($files as $file) {
            $rows[] = [$file, in_array($file, $ran, true) ? 'Ran' : 'Pending'];
        }

        $output->table(['Migration', 'Status'], $rows);

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
