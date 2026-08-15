<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

interface BindableService
{
    public function bindService(): ServiceDefinition;
}
