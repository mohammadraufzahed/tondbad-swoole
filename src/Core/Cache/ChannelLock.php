<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use OpenSwoole\Coroutine\Channel;

class ChannelLock implements Lock
{
    /**
     * @var array<string, Channel>
     */
    private array $channels = [];

    public function acquire(string $key, int $timeoutMs): bool
    {
        if (!isset($this->channels[$key])) {
            $this->channels[$key] = new Channel(1);
        }

        $channel = $this->channels[$key];
        $timeout = $timeoutMs > 0 ? $timeoutMs / 1000.0 : -1;

        return $channel->push(true, $timeout);
    }

    public function release(string $key): void
    {
        if (!isset($this->channels[$key])) {
            return;
        }

        $this->channels[$key]->pop();
    }
}
