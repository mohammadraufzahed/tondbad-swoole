<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Database\Migrations\MigrationCreator;

class MakeMigrationCommand extends Command
{
    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a new migration file.';
    }

    public function run(array $args): int
    {
        if (empty($args)) {
            fwrite(STDERR, "Usage: tondbad make:migration <name> [--create=<table>] [--table=<table>]\n");

            return 1;
        }

        $name = array_shift($args);
        $create = null;
        $table = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--create=')) {
                $create = substr($arg, 9);
                $table = $create;
            } elseif (str_starts_with($arg, '--table=')) {
                $table = substr($arg, 8);
            }
        }

        $path = $this->basePath . '/database/migrations';
        $this->ensureDirectory($path);

        $creator = $this->getCreator();
        $file = $creator->create($name, $path, $table, $create !== null);

        fwrite(STDOUT, "Created: {$file}\n");

        return 0;
    }

    protected function getCreator(): MigrationCreator
    {
        if (app() !== null) {
            return app()->container->make(MigrationCreator::class);
        }

        return new MigrationCreator();
    }
}
