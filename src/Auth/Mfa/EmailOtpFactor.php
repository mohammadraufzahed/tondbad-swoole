<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Mfa;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Database\DatabaseManager;

class EmailOtpFactor implements MfaFactor
{
    private const DIGITS = 6;
    private const TTL = 300;

    public function __construct(private readonly DatabaseManager $databaseManager)
    {
    }

    public function type(): string
    {
        return 'email';
    }

    public function challenge(Authenticatable $user): array
    {
        $code = $this->generateCode();
        $hash = hash('sha256', $code);

        $this->databaseManager->table('mfa_factors')->insert([
            'user_id' => $user->getAuthIdentifier(),
            'type' => $this->type(),
            'config' => json_encode(['hash' => $hash, 'expires_at' => time() + self::TTL], JSON_THROW_ON_ERROR),
            'enabled' => true,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return [
            'code' => $code,
            'expires_at' => time() + self::TTL,
        ];
    }

    public function verify(Authenticatable $user, string $input): bool
    {
        $row = $this->databaseManager
            ->table('mfa_factors')
            ->where('user_id', '=', $user->getAuthIdentifier())
            ->where('type', '=', $this->type())
            ->where('enabled', '=', true)
            ->orderBy('id', 'desc')
            ->first();

        if ($row === null) {
            return false;
        }

        $config = json_decode((string) ($row['config'] ?? '{}'), true);
        $hash = (string) ($config['hash'] ?? '');
        $expiresAt = (int) ($config['expires_at'] ?? 0);

        if ($hash === '' || $expiresAt < time()) {
            return false;
        }

        if (!hash_equals($hash, hash('sha256', $input))) {
            return false;
        }

        $this->databaseManager
            ->table('mfa_factors')
            ->where('id', '=', $row['id'])
            ->update(['enabled' => false]);

        return true;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
