<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Contracts;

use TondbadSwoole\Core\Container;

interface GuardFactory
{
    /**
     * Create a guard instance for the given provider and configuration.
     *
     * @param array<string, mixed> $config
     */
    public function create(Container $container, UserProvider $provider, array $config, string $name): Guard;
}
