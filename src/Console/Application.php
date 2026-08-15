<?php

declare(strict_types=1);

namespace TondbadSwoole\Console;

use InvalidArgumentException;
use TondbadSwoole\Console\Events\ConsoleEvent;
use TondbadSwoole\Events\Contracts\EventDispatcher;

class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    public function __construct(
        private readonly string $basePath,
        private readonly ?EventDispatcher $dispatcher = null,
    ) {
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

        if ($name === '--version' || $name === '-V') {
            $this->printVersion();

            return 0;
        }

        if ($name === null || !isset($this->commands[$name])) {
            $this->emit(new ConsoleEvent('not_found', $name, $argv, $name === null ? 0 : 1));
            $this->printHelp($name);

            return $name === null ? 0 : 1;
        }

        $this->emit(new ConsoleEvent('starting', $name, $argv));

        try {
            $exitCode = $this->commands[$name]->run(array_slice($argv, 2));
        } catch (\Throwable $e) {
            $this->emit(new ConsoleEvent('failed', $name, $argv, 1, $e));

            throw $e;
        }

        $this->emit(new ConsoleEvent('terminated', $name, $argv, $exitCode));

        return $exitCode;
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

    private function printVersion(): void
    {
        fwrite(STDOUT, "Tondbad Swoole version 1.0.0\n");
    }

    private function emit(ConsoleEvent $event): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        if ($this->dispatcher->hasListeners($event) || $this->dispatcher->hasListeners($event->name())) {
            $this->dispatcher->dispatch($event);
        }
    }
}
