<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Exceptions\RevokedRefreshTokenException;
use TondbadSwoole\Auth\Session\RefreshToken;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\ConnectionInterface;
use TondbadSwoole\Database\DatabaseManager;

class RefreshTokenRepository
{
    private int $ttl;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly Config $config,
    ) {
        $this->ttl = (int) $config->get('auth.refresh_token_ttl', 604800);
    }

    public function issue(Session $session): RefreshToken
    {
        $value = bin2hex(random_bytes(32));
        $family = $session->family ?? bin2hex(random_bytes(16));
        $expiresAt = time() + $this->ttl;

        $this->databaseManager->table('refresh_tokens')->insert([
            'session_id' => $session->id,
            'family' => $family,
            'token_hash' => hash('sha256', $value),
            'used_at' => null,
            'revoked' => false,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ]);

        return new RefreshToken($value, $session->id, $family, $expiresAt);
    }

    public function rotate(string $value): RefreshToken
    {
        $hash = hash('sha256', $value);

        return $this->databaseManager->connection()->transaction(function (ConnectionInterface $db) use ($hash) {
            $updated = $db->table('refresh_tokens')
                ->where('token_hash', '=', $hash)
                ->whereNull('used_at')
                ->where('revoked', '=', false)
                ->where('expires_at', '>', time())
                ->update(['used_at' => time()]);

            if ($updated === 0) {
                $row = $db->table('refresh_tokens')->where('token_hash', '=', $hash)->first();

                if ($row !== null) {
                    $this->revokeFamilyInConnection($row['family'], $db);

                    throw new RevokedRefreshTokenException($row['family']);
                }

                throw new RevokedRefreshTokenException();
            }

            $row = $db->table('refresh_tokens')->where('token_hash', '=', $hash)->first();

            if ($row === null) {
                throw new RevokedRefreshTokenException();
            }

            $newValue = bin2hex(random_bytes(32));
            $newExpiresAt = time() + $this->ttl;

            $db->table('refresh_tokens')->insert([
                'session_id' => $row['session_id'],
                'family' => $row['family'],
                'parent' => $row['id'],
                'token_hash' => hash('sha256', $newValue),
                'used_at' => null,
                'revoked' => false,
                'expires_at' => $newExpiresAt,
                'created_at' => time(),
            ]);

            return new RefreshToken($newValue, $row['session_id'], $row['family'], $newExpiresAt);
        });
    }

    public function revokeForSession(string $sessionId): void
    {
        $this->databaseManager->table('refresh_tokens')
            ->where('session_id', '=', $sessionId)
            ->update(['revoked' => true]);
    }

    public function revokeFamily(string $family): void
    {
        $this->databaseManager->table('refresh_tokens')
            ->where('family', '=', $family)
            ->update(['revoked' => true]);
    }

    private function revokeFamilyInConnection(string $family, ConnectionInterface $db): void
    {
        $db->table('refresh_tokens')
            ->where('family', '=', $family)
            ->update(['revoked' => true]);
    }
}
