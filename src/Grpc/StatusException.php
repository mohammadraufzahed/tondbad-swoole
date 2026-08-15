<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class StatusException extends \RuntimeException
{
    public function __construct(public readonly Status $status)
    {
        parent::__construct($status->message, $status->code);
    }
}
