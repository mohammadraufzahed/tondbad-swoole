<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\SessionStores;

use TondbadSwoole\Auth\Contracts\SessionStore;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Database\DatabaseManager;

class DatabaseSessionStore implements SessionStore
{
    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    public function get(string $id): ?Session
    {
        $row = $this->databaseManager->table('sessions')
            ->where('id', '=', $id)
            ->where('status', '=', 'active')
            ->where('expires_at', '>', time())
            ->first();

        if ($row === null) {
            return null;
        }

        return $this->rowToSession($row);
    }

    public function set(Session $session, int $ttl): void
    {
        $claims = $this->encodeClaims($session->claims);
        $existing = $this->databaseManager->table('sessions')->where('id', '=', $session->id)->first();

        $data = [
            'user_id' => $session->userId,
            'claims' => $claims,
            'anti_csrf' => $session->antiCsrf,
            'device' => $session->deviceFingerprint,
            'family' => $session->family,
            'status' => $session->status,
            'expires_at' => $session->expiresAt,
        ];

        if ($existing === null) {
            $this->databaseManager->table('sessions')->insert(array_merge($data, [
                'id' => $session->id,
                'created_at' => $session->createdAt,
            ]));

            return;
        }

        $this->databaseManager->table('sessions')
            ->where('id', '=', $session->id)
            ->update($data);
    }

    public function delete(string $id): void
    {
        $this->databaseManager->table('sessions')->where('id', '=', $id)->delete();
    }

    public function deleteByFamily(string $family): void
    {
        $this->databaseManager->table('sessions')->where('family', '=', $family)->delete();
    }

    public function deleteByUser(string|int $userId): void
    {
        $this->databaseManager->table('sessions')->where('user_id', '=', $userId)->delete();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowToSession(array $row): Session
    {
        $claims = is_string($row['claims'] ?? null)
            ? json_decode($row['claims'], true) ?: []
            : ($row['claims'] ?? []);

        if (!is_array($claims)) {
            $claims = [];
        }

        $userId = $row['user_id'];

        if (is_string($userId) && ctype_digit($userId)) {
            $userId = (int) $userId;
        }

        return new Session(
            (string) $row['id'],
            $userId,
            (int) $row['created_at'],
            (int) $row['expires_at'],
            $claims,
            $row['anti_csrf'] ?? null,
            $row['device'] ?? null,
            $row['family'] ?? null,
            'stateful',
            (string) $row['status'],
        );
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function encodeClaims(array $claims): string
    {
        return json_encode($claims, JSON_THROW_ON_ERROR);
    }
}
