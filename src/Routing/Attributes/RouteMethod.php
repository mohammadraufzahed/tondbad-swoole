<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

interface RouteMethod
{
    public function path(): string;

    public function name(): ?string;

    public function httpMethod(): string;
}
