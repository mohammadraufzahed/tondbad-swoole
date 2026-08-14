<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Contracts;

use TondbadSwoole\Auth\Session\Session;

interface SessionStore
{
    public function get(string $id): ?Session;

    public function set(Session $session, int $ttl): void;

    public function delete(string $id): void;

    public function deleteByFamily(string $family): void;

    /**
     * @param string|int $userId
     */
    public function deleteByUser(string|int $userId): void;
}
