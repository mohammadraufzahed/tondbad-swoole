<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\Migrations\Migrator;

#[AsCommand('migrate', 'Run database migrations.')]
class MigrateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->getMigrator();
        $migrations = $migrator->run();

        if (empty($migrations)) {
            $output->writeln('Nothing to migrate.');
        } else {
            foreach ($migrations as $migration) {
                $output->success("Migrated: {$migration}");
            }
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
