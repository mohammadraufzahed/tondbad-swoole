<?php

declare(strict_types=1);

namespace TondbadSwoole\Console;

use TondbadSwoole\Console\Input\InputDefinition;
use TondbadSwoole\Console\Input\InputInterface;
use TondbadSwoole\Console\Output\OutputInterface;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @return list<string>
     */
    public function getAliases(): array;

    public function isCoroutine(): bool;

    public function getAuthorizeAbility(): ?string;

    public function getAuthorizeGuard(): ?string;

    public function getDefinition(): InputDefinition;

    public function run(InputInterface $input, OutputInterface $output): int;
}
