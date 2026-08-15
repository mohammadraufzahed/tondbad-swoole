<?php

declare(strict_types=1);

namespace TondbadSwoole\Console;

use InvalidArgumentException;
use OpenSwoole\Coroutine;
use OpenSwoole\Runtime;
use TondbadSwoole\Console\Events\ConsoleEvent;
use TondbadSwoole\Console\Input\ArgvInput;
use TondbadSwoole\Console\Input\InputDefinition;
use TondbadSwoole\Console\Output\ConsoleOutput;
use TondbadSwoole\Console\Output\OutputInterface;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;

class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    /**
     * @var array<string, string>
     */
    private array $aliases = [];

    public function __construct(
        private readonly string $basePath,
        private readonly ?EventDispatcher $dispatcher = null,
        private readonly ?Container $container = null,
    ) {
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function register(CommandInterface $command): self
    {
        $name = $command->getName();

        if ($name === '') {
            throw new InvalidArgumentException('A command must have a name.');
        }

        $this->commands[$name] = $command;

        foreach ($command->getAliases() as $alias) {
            $this->aliases[$alias] = $name;
        }

        return $this;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv, ?OutputInterface $output = null): int
    {
        $output ??= new ConsoleOutput();

        $global = $this->parseGlobalOptions($argv, $commandName, $output);

        if ($global['version']) {
            $this->printVersion($output);

            return 0;
        }

        if ($commandName === null) {
            $this->printGlobalHelp($output, null);

            return $global['help'] ? 0 : 1;
        }

        $command = $this->findCommand($commandName);

        if ($command === null) {
            $this->emit(new ConsoleEvent('not_found', $commandName, $argv, 1));
            $this->printGlobalHelp($output, $commandName);

            return 1;
        }

        $input = new ArgvInput($global['commandTokens'], $command->getDefinition());

        if ($global['help']) {
            $this->printCommandHelp($command, $output);

            return 0;
        }

        return $this->runCommand($command, $input, $output);
    }

    /**
     * @return list<string>
     */
    public function getCommandNames(): array
    {
        return array_keys($this->commands);
    }

    public function findCommand(string $name): ?CommandInterface
    {
        if (isset($this->commands[$name])) {
            return $this->commands[$name];
        }

        if (isset($this->aliases[$name])) {
            return $this->commands[$this->aliases[$name]];
        }

        return null;
    }

    private function runCommand(CommandInterface $command, ArgvInput $input, OutputInterface $output): int
    {
        $ability = $command->getAuthorizeAbility();

        if ($ability !== null && !$this->checkAuthorization($ability, $command->getAuthorizeGuard())) {
            $output->error("You are not authorized to run this command (ability: {$ability}).");

            return 1;
        }

        $this->emit(new ConsoleEvent('starting', $command->getName(), $input->getTokens()));

        try {
            $exitCode = $this->runInCoroutineIfNeeded($command, $input, $output);
        } catch (\Throwable $e) {
            $this->emit(new ConsoleEvent('failed', $command->getName(), $input->getTokens(), 1, $e));
            $output->error($e->getMessage());

            return 1;
        }

        $this->emit(new ConsoleEvent('terminated', $command->getName(), $input->getTokens(), $exitCode));

        return $exitCode;
    }

    private function runInCoroutineIfNeeded(CommandInterface $command, ArgvInput $input, OutputInterface $output): int
    {
        if (!$command->isCoroutine()) {
            return $command->run($input, $output);
        }

        if (!class_exists(Coroutine::class) || !method_exists(Coroutine::class, 'run') || Coroutine::getCid() !== -1) {
            return $command->run($input, $output);
        }

        if (class_exists(Runtime::class) && defined('SWOOLE_HOOK_ALL')) {
            $flags = (int) Runtime::getHookFlags();

            if (($flags & SWOOLE_HOOK_ALL) !== SWOOLE_HOOK_ALL) {
                Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
            }
        }

        $exitCode = 0;
        $app = $this;

        Coroutine::run(function () use ($app, $command, $input, $output, &$exitCode): void {
            try {
                $exitCode = $command->run($input, $output);
            } catch (\Throwable $e) {
                $app->emit(new ConsoleEvent('failed', $command->getName(), $input->getTokens(), 1, $e));
                $output->error($e->getMessage());
                $exitCode = 1;
            }
        });

        return $exitCode;
    }

    /**
     * @param list<string> $argv
     * @return array{help: bool, version: bool, quiet: bool, verbose: int, ansi: bool|null, commandTokens: list<string>}
     */
    private function parseGlobalOptions(array $argv, ?string &$commandName, OutputInterface $output): array
    {
        $global = [
            'help' => false,
            'version' => false,
            'quiet' => false,
            'verbose' => 0,
            'ansi' => null,
            'commandTokens' => [],
        ];

        $commandName = null;
        $commandIndex = null;
        $globalIndices = [0];
        $skipNext = false;

        foreach ($argv as $index => $arg) {
            if ($index === 0) {
                continue;
            }

            if ($skipNext) {
                $globalIndices[] = $index;
                $skipNext = false;
                continue;
            }

            if ($arg === '--env' || $arg === '-e') {
                $globalIndices[] = $index;
                $skipNext = true;
                continue;
            }

            if (str_starts_with($arg, '--env=')) {
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--help' || $arg === '-h') {
                $global['help'] = true;
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--version' || $arg === '-V') {
                $global['version'] = true;
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--quiet' || $arg === '-q') {
                $global['quiet'] = true;
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--verbose') {
                $global['verbose']++;
                $globalIndices[] = $index;
                continue;
            }

            if (preg_match('/^(-v+)$/', $arg, $matches)) {
                $global['verbose'] += strlen($matches[1]) - 1;
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--ansi') {
                $global['ansi'] = true;
                $globalIndices[] = $index;
                continue;
            }

            if ($arg === '--no-ansi') {
                $global['ansi'] = false;
                $globalIndices[] = $index;
                continue;
            }

            if (str_starts_with($arg, '-')) {
                continue;
            }

            if ($commandName === null) {
                $commandName = $arg;
                $commandIndex = $index;
            }
        }

        if ($commandIndex !== null) {
            $global['commandTokens'] = array_values(array_filter(
                $argv,
                fn (int $index) => $index > 0 && $index !== $commandIndex && !in_array($index, $globalIndices, true),
                ARRAY_FILTER_USE_KEY,
            ));
        }

        if ($global['ansi'] !== null) {
            $output->setAnsi($global['ansi']);
        }

        if ($global['quiet']) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        } elseif ($global['verbose'] > 0) {
            $level = match (min($global['verbose'], 3)) {
                1 => OutputInterface::VERBOSITY_VERBOSE,
                2 => OutputInterface::VERBOSITY_VERY_VERBOSE,
                default => OutputInterface::VERBOSITY_DEBUG,
            };
            $output->setVerbosity($level);
        }

        return $global;
    }

    private function printGlobalHelp(OutputInterface $output, ?string $unknownName): void
    {
        if ($unknownName !== null) {
            $output->error("Unknown command: {$unknownName}");
            $output->newLine();
        }

        $output->title('Tondbad Swoole CLI');
        $output->writeln('Usage:');
        $output->writeln('  php bin/tondbad <command> [options] [arguments]');
        $output->newLine();

        if (empty($this->commands)) {
            $output->writeln('No commands registered.');

            return;
        }

        $output->writeln('Available commands:');

        $names = $this->getCommandNames();
        sort($names);

        $groups = [];
        foreach ($names as $name) {
            $command = $this->commands[$name];
            $group = str_contains($name, ':') ? explode(':', $name, 2)[0] : '_';
            $groups[$group][] = $command;
        }

        if (isset($groups['_'])) {
            $output->section('General');

            foreach ($groups['_'] as $command) {
                $output->writeln(sprintf('  %-24s %s', $command->getName(), $command->getDescription()));
            }
        }

        foreach ($groups as $group => $commands) {
            if ($group === '_') {
                continue;
            }

            $output->section($group);

            foreach ($commands as $command) {
                $output->writeln(sprintf('  %-24s %s', $command->getName(), $command->getDescription()));
            }
        }

        $output->newLine();
    }

    private function printCommandHelp(CommandInterface $command, OutputInterface $output): void
    {
        $output->writeln($command->getDefinition()->getHelp($command->getName(), $command->getDescription(), $command->getAliases()));
    }

    private function printVersion(OutputInterface $output): void
    {
        $output->writeln('Tondbad Swoole version 1.0.0');
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

    private function checkAuthorization(string $ability, ?string $guard): bool
    {
        if ($this->container === null) {
            return true;
        }

        $gateClass = 'TondbadSwoole\\Auth\\Access\\Gate';

        if (!class_exists($gateClass) || !$this->container->has($gateClass)) {
            return true;
        }

        /** @var \TondbadSwoole\Auth\Access\Gate $gate */
        $gate = $this->container->make($gateClass);

        return $gate->allows($ability);
    }
}
