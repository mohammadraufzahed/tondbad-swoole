<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Contracts;

use TondbadSwoole\Core\Container;
use TondbadSwoole\Scheduling\ScheduleRegistry;

interface Task
{
    public function execute(Container $container, string $basePath, ?ScheduleRegistry $registry = null): mixed;

    public function toArray(): array;
}
