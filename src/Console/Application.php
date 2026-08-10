<?php

declare(strict_types=1);

namespace TondbadSwoole\Console;

use InvalidArgumentException;

class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    public function __construct(private readonly string $basePath)
    {
    }

    public function register(CommandInterface $command): self
    {
        $this->commands[$command->getName()] = $command;

        return $this;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? null;

        if ($name === null || !isset($this->commands[$name])) {
            $this->printHelp($name);

            return $name === null ? 0 : 1;
        }

        return $this->commands[$name]->run(array_slice($argv, 2));
    }

    private function printHelp(?string $unknownName): void
    {
        if ($unknownName !== null) {
            fwrite(STDERR, "Unknown command: {$unknownName}\n\n");
        }

        fwrite(STDOUT, "Tondbad Swoole CLI\n\nAvailable commands:\n");

        foreach ($this->commands as $command) {
            fwrite(STDOUT, sprintf("  %-20s %s\n", $command->getName(), $command->getDescription()));
        }

        fwrite(STDOUT, "\n");
    }
}
