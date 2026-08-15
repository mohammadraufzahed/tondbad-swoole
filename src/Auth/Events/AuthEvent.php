<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Events;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Auth\Session\AuthSession;
use TondbadSwoole\Events\Event;

final class AuthEvent extends Event
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly ?Authenticatable $user = null,
        public readonly ?AuthSession $session = null,
        public readonly ?string $guard = null,
        public readonly ?string $userId = null,
        public readonly array $metadata = [],
    ) {
    }

    public function name(): string
    {
        return 'auth.' . $this->type;
    }
}
