<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Input;

use TondbadSwoole\Console\ConsoleException;

final class ArgvInput implements InputInterface
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    /** @var array<int, mixed> */
    private array $positionals = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private ?string $firstArgument = null;
    private bool $parsed = false;

    /**
     * @param list<string|int> $tokens
     */
    public function __construct(
        private readonly array $tokens,
        private readonly InputDefinition $definition,
    ) {
    }

    public function parse(): void
    {
        if ($this->parsed) {
            return;
        }

        $positionals = [];
        $stopOptions = false;
        $len = count($this->tokens);

        for ($i = 0; $i < $len; $i++) {
            $token = (string) $this->tokens[$i];

            if ($token === '--') {
                $stopOptions = true;
                continue;
            }

            if ($stopOptions) {
                $positionals[] = $token;
                continue;
            }

            if ($token === '-') {
                $positionals[] = $token;
                continue;
            }

            if (str_starts_with($token, '--')) {
                $i = $this->parseLongOption($token, $i, $len);
                continue;
            }

            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $i = $this->parseShortOption($token, $i, $len);
                continue;
            }

            $positionals[] = $token;
        }

        $this->positionals = $positionals;
        $this->firstArgument = $positionals[0] ?? null;
        $this->parsed = true;
    }

    /**
     * @return array<int, mixed>
     */
    public function getPositionals(): array
    {
        return $this->positionals;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    public function hasArgument(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    public function getFirstArgument(): ?string
    {
        return $this->firstArgument;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function setArgument(string $name, mixed $value): void
    {
        $argument = $this->definition->getArgument($name);

        if ($argument->isArray()) {
            if (!isset($this->arguments[$name])) {
                $this->arguments[$name] = [];
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $this->arguments[$name][] = $item;
                }
            } else {
                $this->arguments[$name][] = $value;
            }

            return;
        }

        $this->arguments[$name] = $value;
    }

    public function setOption(string $name, mixed $value): void
    {
        $option = $this->definition->getOption($name);

        if ($option->isArray()) {
            if (!isset($this->options[$name])) {
                $this->options[$name] = [];
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $this->options[$name][] = $item;
                }
            } else {
                $this->options[$name][] = $value;
            }

            return;
        }

        $this->options[$name] = $value;
    }

    private function parseLongOption(string $token, int $index, int $len): int
    {
        $body = substr($token, 2);

        if (str_contains($body, '=')) {
            [$name, $value] = explode('=', $body, 2);
            $this->setLongOption($name, $value);

            return $index;
        }

        if ($this->definition->hasNegatedOption($body)) {
            $this->setOption($this->definition->getNegatedOptionName($body), false);

            return $index;
        }

        $this->setLongOption($body, $this->resolveLongOptionValue($body, $index, $len));

        return $index;
    }

    private function resolveLongOptionValue(string $name, int $index, int $len): mixed
    {
        if (!$this->definition->hasOption($name)) {
            $next = isset($this->tokens[$index + 1]) ? (string) $this->tokens[$index + 1] : null;

            if ($next !== null && !$this->isOption($next)) {
                return (string) $this->tokens[++$index];
            }

            return true;
        }

        $option = $this->definition->getOption($name);

        if ($option->isValueRequired()) {
            $next = isset($this->tokens[$index + 1]) ? (string) $this->tokens[$index + 1] : null;

            if ($next === null || $this->isOption($next)) {
                throw new ConsoleException("Option --{$name} requires a value.");
            }

            return (string) $this->tokens[++$index];
        }

        if ($option->isValueOptional()) {
            $next = isset($this->tokens[$index + 1]) ? (string) $this->tokens[$index + 1] : null;

            if ($next !== null && !$this->isOption($next)) {
                return (string) $this->tokens[++$index];
            }

            return $option->default;
        }

        if ($option->isValueNone()) {
            return ($this->options[$name] ?? 0) + 1;
        }

        return true;
    }

    private function setLongOption(string $name, mixed $value): void
    {
        if (!$this->definition->hasOption($name)) {
            throw new ConsoleException("Unknown option: --{$name}");
        }

        $this->setOption($name, $value);
    }

    private function parseShortOption(string $token, int $index, int $len): int
    {
        $chars = substr($token, 1);
        $charLen = strlen($chars);

        for ($i = 0; $i < $charLen; $i++) {
            $char = $chars[$i];
            $option = $this->definition->getOptionForShortcut($char);

            if ($option === null) {
                throw new ConsoleException("Unknown option: -{$char}");
            }

            if ($option->isValueNone()) {
                $this->setOption($option->name, ($this->options[$option->name] ?? 0) + 1);
                continue;
            }

            $rest = substr($chars, $i + 1);

            if ($rest !== '') {
                $this->setOption($option->name, $rest);

                return $index;
            }

            $next = isset($this->tokens[$index + 1]) ? (string) $this->tokens[$index + 1] : null;

            if ($next !== null && !$this->isOption($next)) {
                $this->setOption($option->name, (string) $this->tokens[++$index]);

                return $index;
            }

            if ($option->isValueRequired()) {
                throw new ConsoleException("Option -{$char} requires a value.");
            }

            $this->setOption($option->name, $option->default);

            return $index;
        }

        return $index;
    }

    private function isOption(string $token): bool
    {
        return str_starts_with($token, '-') && $token !== '-';
    }
}
