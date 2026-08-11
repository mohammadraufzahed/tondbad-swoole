<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Database\Migrations\Migrator;

class MigrateStatusCommand extends Command
{
    public function getName(): string
    {
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Show the status of each migration.';
    }

    public function run(array $args): int
    {
        $migrator = $this->getMigrator();
        $path = $this->basePath . '/database/migrations';
        $files = $migrator->getMigrationFiles($path);
        $ran = app()->container->make(\TondbadSwoole\Database\Migrations\MigrationRepository::class)->getRan();

        fwrite(STDOUT, str_pad('Migration', 50) . ' Status' . "\n");
        fwrite(STDOUT, str_repeat('-', 60) . "\n");

        foreach ($files as $file) {
            $status = in_array($file, $ran, true) ? 'Ran' : 'Pending';
            fwrite(STDOUT, str_pad($file, 50) . ' ' . $status . "\n");
        }

        return 0;
    }

    protected function getMigrator(): Migrator
    {
        return app()->container->make(Migrator::class);
    }
}
