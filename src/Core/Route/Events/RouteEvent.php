<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route\Events;

use TondbadSwoole\Events\Event;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

final class RouteEvent extends Event
{
    /**
     * @param array<string, mixed>|null $vars
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly Request $request,
        public readonly Response $response,
        public readonly ?array $vars = null,
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'route.' . $this->type;
    }
}
