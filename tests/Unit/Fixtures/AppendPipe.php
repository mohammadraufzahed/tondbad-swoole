<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Fixtures;

use TondbadSwoole\Core\Pipeline\Contracts\PipeInterface;

class AppendPipe implements PipeInterface
{
    public function __construct(private readonly string $suffix)
    {
    }

    public function handle(mixed $passable, \Closure $next): mixed
    {
        return $next($passable . $this->suffix);
    }
}
