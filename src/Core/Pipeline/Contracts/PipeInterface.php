<?php

namespace TondbadSwoole\Core\Pipeline\Contracts;

interface PipeInterface
{
    public function handle($passable, \Closure $next): mixed;
}