<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Compilers;

final class Token
{
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public array $data = [],
    ) {
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
