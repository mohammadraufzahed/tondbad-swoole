<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Compilers;

final class CompilerBlock
{
    public int $id;

    public string $state = '';

    public string $content = '';

    public function __construct(
        public readonly string $type,
        public readonly string $args = '',
    ) {
        $this->id = random_int(1, PHP_INT_MAX);
    }

    public function append(string $text): void
    {
        $this->content .= $text;
    }
}
