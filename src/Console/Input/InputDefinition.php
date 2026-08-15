<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Input;

use TondbadSwoole\Console\ConsoleException;
use TondbadSwoole\Validation\Schema;

final class InputDefinition
{
    /** @var array<string, InputArgument> */
    private array $arguments = [];

    /** @var array<string, InputOption> */
    private array $options = [];

    /** @var array<string, string> */
    private array $shortcuts = [];

    private bool $globalOptionsAdded = false;

    public function addArgument(InputArgument $argument): self
    {
        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    public function addOption(InputOption $option): self
    {
        if (isset($this->options[$option->name])) {
            throw new ConsoleException("An option named \"{$option->name}\" already exists.");
        }

        $this->options[$option->name] = $option;

        if ($option->shortcut !== null && $option->shortcut !== '') {
            if (isset($this->shortcuts[$option->shortcut])) {
                throw new ConsoleException("An option shortcut \"{$option->shortcut}\" already exists.");
            }

            $this->shortcuts[$option->shortcut] = $option->name;
        }

        return $this;
    }

    public function getArgument(string $name): InputArgument
    {
        if (!isset($this->arguments[$name])) {
            throw new ConsoleException("The \"{$name}\" argument does not exist.");
        }

        return $this->arguments[$name];
    }

    public function getOption(string $name): InputOption
    {
        if (!isset($this->options[$name])) {
            throw new ConsoleException("The \"{$name}\" option does not exist.");
        }

        return $this->options[$name];
    }

    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function getOptionForShortcut(string $shortcut): ?InputOption
    {
        return isset($this->shortcuts[$shortcut])
            ? $this->options[$this->shortcuts[$shortcut]]
            : null;
    }

    public function hasNegatedOption(string $name): bool
    {
        $negatedName = $this->getNegatedOptionName($name);

        return $negatedName !== null && $this->hasOption($negatedName);
    }

    public function getNegatedOptionName(string $name): ?string
    {
        if (!str_starts_with($name, 'no-')) {
            return null;
        }

        $candidate = substr($name, 3);

        return $this->hasOption($candidate) && $this->getOption($candidate)->isNegatable()
            ? $candidate
            : null;
    }

    /**
     * @return array<string, InputArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, InputOption>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function addGlobalOptions(): void
    {
        if ($this->globalOptionsAdded) {
            return;
        }

        $this->addOption(new InputOption('help', 'h', InputOption::VALUE_NONE, 'Display help for the command'));
        $this->addOption(new InputOption('quiet', 'q', InputOption::VALUE_NONE, 'Do not output any message'));
        $this->addOption(new InputOption('verbose', 'v', InputOption::VALUE_NONE, 'Increase the verbosity of messages'));
        $this->addOption(new InputOption('ansi', null, InputOption::VALUE_NONE, 'Force ANSI output'));
        $this->addOption(new InputOption('no-ansi', null, InputOption::VALUE_NONE, 'Disable ANSI output'));

        $this->globalOptionsAdded = true;
    }

    public function bind(ArgvInput $input): void
    {
        $input->parse();

        $positionals = $input->getPositionals();
        $index = 0;

        foreach ($this->arguments as $name => $argument) {
            if ($argument->isArray()) {
                $value = array_slice($positionals, $index);

                if ($value !== []) {
                    $input->setArgument($name, $this->coerce($argument->schema, $value, $name));
                } elseif ($argument->isDefaultValueAvailable()) {
                    $input->setArgument($name, $argument->default);
                } elseif ($argument->isRequired()) {
                    throw new ConsoleException("Missing required argument \"{$name}\".");
                } else {
                    $input->setArgument($name, []);
                }

                $index = count($positionals);
            } elseif (isset($positionals[$index])) {
                $input->setArgument($name, $this->coerce($argument->schema, $positionals[$index], $name));
                $index++;
            } elseif ($argument->isDefaultValueAvailable()) {
                $input->setArgument($name, $argument->default);
            } elseif ($argument->isRequired()) {
                throw new ConsoleException("Missing required argument \"{$name}\".");
            } else {
                $input->setArgument($name, null);
            }
        }

        if ($index < count($positionals)) {
            throw new ConsoleException('Too many arguments provided.');
        }

        foreach ($this->options as $name => $option) {
            if ($input->hasOption($name)) {
                $input->setOption($name, $this->coerce($option->schema, $input->getOption($name), $name));
            } elseif ($option->default !== null) {
                $input->setOption($name, $this->coerce($option->schema, $option->default, $name));
            } else {
                $input->setOption($name, $option->isValueNone() ? false : null);
            }
        }

        $this->validate($input);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function getSynopsis(string $commandName, array $arguments = []): string
    {
        $usage = $commandName;

        foreach ($this->arguments as $argument) {
            $open = $argument->isRequired() ? '<' : '[';
            $close = $argument->isRequired() ? '>' : ']';
            $name = strtoupper($argument->name);
            if ($argument->isArray()) {
                $name = "{$name}1 [{$name}2 ...]";
            }
            $usage .= " {$open}{$name}{$close}";
        }

        $usage .= ' [options]';

        return $usage;
    }

    public function getHelp(string $commandName, ?string $description = null, array $aliases = []): string
    {
        $lines = ["Usage:"];
        $lines[] = '  ' . $this->getSynopsis($commandName);

        if ($description !== null && $description !== '') {
            $lines[] = '';
            $lines[] = $description;
        }

        if (!empty($aliases)) {
            $lines[] = '';
            $lines[] = 'Aliases: ' . implode(', ', $aliases);
        }

        if (!empty($this->arguments)) {
            $lines[] = '';
            $lines[] = 'Arguments:';
            foreach ($this->arguments as $argument) {
                $mode = $argument->isRequired() ? 'required' : 'optional';
                if ($argument->isArray()) {
                    $mode .= ', array';
                }
                $default = $argument->isDefaultValueAvailable() ? ' (default: ' . json_encode($argument->default) . ')' : '';
                $lines[] = sprintf('  %-20s %s%s', $argument->name, $argument->description, " [{$mode}]{$default}");
            }
        }

        if (!empty($this->options)) {
            $lines[] = '';
            $lines[] = 'Options:';
            foreach ($this->options as $option) {
                $shortcut = $option->shortcut !== null ? "-{$option->shortcut}, " : '    ';
                $mode = $this->optionModeLabel($option);
                $default = $option->default !== null ? ' (default: ' . json_encode($option->default) . ')' : '';
                $lines[] = sprintf('  %s--%-18s %s%s [%s]', $shortcut, $option->name, $option->description, $default, $mode);
            }
        }

        return implode(PHP_EOL, $lines);
    }

    private function validate(ArgvInput $input): void
    {
        $argumentCount = count($input->getArguments());
        $expected = 0;

        foreach ($this->arguments as $argument) {
            if ($argument->isArray()) {
                $expected = -1;
                break;
            }

            $expected++;
        }

        if ($expected >= 0 && $argumentCount > $expected) {
            throw new ConsoleException('Too many arguments provided.');
        }
    }

    private function coerce(?Schema $schema, mixed $value, string $name): mixed
    {
        if ($schema === null) {
            return $value;
        }

        $result = $schema->safeParse($value);

        if (!$result->valid) {
            $messages = implode('; ', array_column($result->errors, 'message'));
            throw new ConsoleException("Invalid value for \"{$name}\": {$messages}");
        }

        return $result->data;
    }

    private function optionModeLabel(InputOption $option): string
    {
        if ($option->isValueNone()) {
            return 'none';
        }

        if ($option->isValueRequired()) {
            return $option->isArray() ? 'array (required)' : 'required';
        }

        if ($option->isValueOptional()) {
            return 'optional';
        }

        return 'none';
    }
}
