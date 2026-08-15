<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

interface Stream
{
    public function recv(): ?object;

    public function send(object $message): void;

    public function close(?Status $status = null): void;
}
