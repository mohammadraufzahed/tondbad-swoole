<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Commands;

use TondbadSwoole\Console\Attributes\AsCommand;
use TondbadSwoole\Console\Attributes\Argument;
use TondbadSwoole\Console\Attributes\Option;
use TondbadSwoole\Console\Input\InputArgument;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Input\InputOption;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Database\Migrations\MigrationCreator;
use TondbadSwoole\Database\Migrations\MigrationPathManager;

#[AsCommand('make:migration', 'Create a new migration file.')]
class MakeMigrationCommand extends Command
{
    #[Argument('name', mode: InputArgument::REQUIRED, description: 'Migration name')]
    public string $name;

    #[Option('create', mode: InputOption::VALUE_OPTIONAL, description: 'Create a new table', default: null)]
    public ?string $create = null;

    #[Option('table', mode: InputOption::VALUE_OPTIONAL, description: 'Modify an existing table', default: null)]
    public ?string $table = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $create = $this->create;
        $table = $this->table;
        $isCreate = false;

        foreach ($input->getTokens() as $token) {
            if ($token === '--create') {
                $isCreate = true;

                if ($create === null) {
                    $create = $this->name;
                }
            } elseif (str_starts_with($token, '--create=')) {
                $isCreate = true;
                $create = substr($token, 9);
            }
        }

        if ($isCreate) {
            $table = $create ?? $this->name;
        }

        $path = $this->getDefaultPath();
        $this->ensureDirectory($path);

        $creator = $this->getCreator();
        $file = $creator->create($this->name, $path, $table, $isCreate);

        $output->success("Created: {$file}");

        return 0;
    }

    protected function getDefaultPath(): string
    {
        if (app() !== null) {
            $manager = app()->container->make(MigrationPathManager::class);
            $default = $manager->getDefaultPath();

            if ($default !== '') {
                return $default;
            }
        }

        return $this->basePath . '/database/migrations';
    }

    protected function getCreator(): MigrationCreator
    {
        if (app() !== null) {
            return app()->container->make(MigrationCreator::class);
        }

        return new MigrationCreator();
    }
}
