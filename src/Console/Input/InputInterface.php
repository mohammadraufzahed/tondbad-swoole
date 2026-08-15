<?php

declare(strict_types=1);

namespace TondbadSwoole\Console\Input;

interface InputInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array;

    public function getArgument(string $name): mixed;

    public function hasArgument(string $name): bool;

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    public function getOption(string $name): mixed;

    public function hasOption(string $name): bool;

    public function getFirstArgument(): ?string;

    /**
     * @return list<string>
     */
    public function getTokens(): array;
}
