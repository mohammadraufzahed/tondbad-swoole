<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Jobs\Backoff;

interface BackoffStrategy
{
    public function delay(int $attempts): int;

    public function getTypeForStorage(): string;

    public function getValueForStorage(): int;
}
