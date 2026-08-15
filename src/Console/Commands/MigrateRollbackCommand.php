<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\Migrations\Migrator;

#[AsCommand('migrate:rollback', 'Rollback the last batch of migrations.')]
class MigrateRollbackCommand extends Command
{
    #[Option('step', shortcut: 's', mode: InputOption::VALUE_OPTIONAL, schema: 'int', description: 'Number of batches to rollback')]
    public ?int $step = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrator = $this->getMigrator();
        $migrations = $migrator->rollback($this->step);

        if (empty($migrations)) {
            $output->writeln('Nothing to rollback.');
        } else {
            foreach ($migrations as $migration) {
                $output->success("Rolled back: {$migration}");
            }
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
