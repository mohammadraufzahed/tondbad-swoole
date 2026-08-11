<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Database\Migrations\Migrator;

class MigrateRollbackCommand extends Command
{
    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last batch of migrations.';
    }

    public function run(array $args): int
    {
        $migrator = $this->getMigrator();
        $steps = $this->resolveSteps($args);
        $migrations = $migrator->rollback($steps);

        if (empty($migrations)) {
            fwrite(STDOUT, "Nothing to rollback.\n");
        } else {
            foreach ($migrations as $migration) {
                fwrite(STDOUT, "Rolled back: {$migration}\n");
            }
        }

        return 0;
    }

    protected function resolveSteps(array $args): ?int
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--step=')) {
                return (int) substr($arg, 7);
            }
        }

        return null;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
