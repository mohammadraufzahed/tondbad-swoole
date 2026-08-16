<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

final class LiveUpdate
{
    public function __construct(
        public readonly string $html,
        public readonly string $token,
    ) {
    }
}
