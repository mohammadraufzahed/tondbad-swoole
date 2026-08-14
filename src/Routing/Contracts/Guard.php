<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Contracts;

use TondbadSwoole\Http\Request;

interface Guard
{
    public function can(Request $request): bool;
}
