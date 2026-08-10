<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Pipeline\Contracts;

interface PipeInterface
{
    /**
     * Process the passable value and optionally pass it to the next pipe.
     *
     * @param mixed $passable
     * @param \Closure $next
     * @return mixed
     */
    public function handle(mixed $passable, \Closure $next): mixed;
}
