<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\SessionStores;

use TondbadSwoole\Auth\Contracts\SessionStore;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Core\Cache\RedisCache;

class RedisSessionStore implements SessionStore
{
    private const KEY_PREFIX = 'auth:session:';
    private const FAMILY_SET_PREFIX = 'auth:session:family:';
    private const USER_SET_PREFIX = 'auth:session:user:';

    public function __construct(private readonly RedisCache $redis)
    {
    }

    public function get(string $id): ?Session
    {
        $data = $this->redis->get(self::KEY_PREFIX . $id);

        if (!is_array($data)) {
            return null;
        }

        $session = $this->arrayToSession($data);

        if ($session->status !== 'active' || $session->expiresAt < time()) {
            return null;
        }

        return $session;
    }

    public function set(Session $session, int $ttl): void
    {
        if ($ttl <= 0) {
            $this->delete($session->id);

            return;
        }

        $this->redis->set(self::KEY_PREFIX . $session->id, $this->sessionToArray($session), $ttl);

        if ($session->family !== null) {
            $this->redis->execute(function ($client) use ($session, $ttl): void {
                $key = self::FAMILY_SET_PREFIX . $session->family;
                $client->sadd($key, [$session->id]);
                $client->expire($key, $ttl);
            });
        }

        $this->redis->execute(function ($client) use ($session, $ttl): void {
            $key = self::USER_SET_PREFIX . $session->userId;
            $client->sadd($key, [$session->id]);
            $client->expire($key, $ttl);
        });
    }

    public function delete(string $id): void
    {
        $session = $this->get($id);

        $this->redis->delete(self::KEY_PREFIX . $id);

        if ($session === null) {
            return;
        }

        $this->removeFromIndex($session);
    }

    public function deleteByFamily(string $family): void
    {
        $sessionIds = $this->redis->execute(function ($client) use ($family): array {
            $key = self::FAMILY_SET_PREFIX . $family;
            $members = $client->smembers($key);
            $client->del([$key]);

            return is_array($members) ? $members : [];
        });

        foreach ($sessionIds as $sessionId) {
            $session = $this->get((string) $sessionId);

            if ($session !== null) {
                $this->delete($session->id);
            }
        }
    }

    public function deleteByUser(string|int $userId): void
    {
        $sessionIds = $this->redis->execute(function ($client) use ($userId): array {
            $key = self::USER_SET_PREFIX . $userId;
            $members = $client->smembers($key);
            $client->del([$key]);

            return is_array($members) ? $members : [];
        });

        foreach ($sessionIds as $sessionId) {
            $this->delete((string) $sessionId);
        }
    }

    private function removeFromIndex(Session $session): void
    {
        $this->redis->execute(function ($client) use ($session): void {
            if ($session->family !== null) {
                $client->srem(self::FAMILY_SET_PREFIX . $session->family, [$session->id]);
            }

            $client->srem(self::USER_SET_PREFIX . $session->userId, [$session->id]);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToSession(array $data): Session
    {
        return new Session(
            (string) ($data['id'] ?? ''),
            $data['userId'] ?? ($data['user_id'] ?? ''),
            (int) ($data['createdAt'] ?? ($data['created_at'] ?? time())),
            (int) ($data['expiresAt'] ?? ($data['expires_at'] ?? time())),
            is_array($data['claims'] ?? null) ? $data['claims'] : [],
            $data['antiCsrf'] ?? ($data['anti_csrf'] ?? null),
            $data['deviceFingerprint'] ?? ($data['device'] ?? null),
            $data['family'] ?? null,
            (string) ($data['mode'] ?? 'stateful'),
            (string) ($data['status'] ?? 'active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionToArray(Session $session): array
    {
        return [
            'id' => $session->id,
            'user_id' => $session->userId,
            'created_at' => $session->createdAt,
            'expires_at' => $session->expiresAt,
            'claims' => $session->claims,
            'anti_csrf' => $session->antiCsrf,
            'device' => $session->deviceFingerprint,
            'family' => $session->family,
            'mode' => $session->mode,
            'status' => $session->status,
        ];
    }
}
