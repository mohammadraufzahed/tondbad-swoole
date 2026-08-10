<?php

declare(strict_types=1);

namespace TondbadSwoole\Console;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param list<string> $args
     */
    public function run(array $args): int;
}
