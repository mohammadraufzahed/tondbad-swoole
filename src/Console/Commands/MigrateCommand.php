<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Database\Migrations\Migrator;

class MigrateCommand extends Command
{
    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run database migrations.';
    }

    public function run(array $args): int
    {
        $migrator = $this->getMigrator();
        $migrations = $migrator->run();

        if (empty($migrations)) {
            fwrite(STDOUT, "Nothing to migrate.\n");
        } else {
            foreach ($migrations as $migration) {
                fwrite(STDOUT, "Migrated: {$migration}\n");
            }
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
