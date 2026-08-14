<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Mfa;

use TondbadSwoole\Auth\Contracts\Authenticatable;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Database\DatabaseManager;

class TotpFactor implements MfaFactor
{
    private const DIGITS = 6;
    private const INTERVAL = 30;

    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $databaseManager,
    ) {
    }

    public function type(): string
    {
        return 'totp';
    }

    public function challenge(Authenticatable $user): array
    {
        $secret = Base32::encode(random_bytes(20));
        $identifier = $user->getAuthIdentifierName() ?? 'user';
        $issuer = (string) $this->config->get('app.name', 'Tondbad');

        $config = [
            'secret' => $secret,
            'qr_uri' => "otpauth://totp/{$issuer}:{$identifier}?secret={$secret}&issuer={$issuer}",
        ];

        $this->databaseManager->table('mfa_factors')->insert([
            'user_id' => $user->getAuthIdentifier(),
            'type' => $this->type(),
            'config' => json_encode($config, JSON_THROW_ON_ERROR),
            'enabled' => true,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return $config;
    }

    public function verify(Authenticatable $user, string $input): bool
    {
        $row = $this->databaseManager
            ->table('mfa_factors')
            ->where('user_id', '=', $user->getAuthIdentifier())
            ->where('type', '=', $this->type())
            ->where('enabled', '=', true)
            ->first();

        if ($row === null) {
            return false;
        }

        $config = json_decode((string) ($row['config'] ?? '{}'), true);
        $secret = (string) ($config['secret'] ?? '');

        if ($secret === '') {
            return false;
        }

        $timestamp = time();

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals((string) $this->totp($secret, $timestamp + ($offset * self::INTERVAL)), $input)) {
                return true;
            }
        }

        return false;
    }

    private function totp(string $secret, int $timestamp): int
    {
        $counter = (int) floor($timestamp / self::INTERVAL);
        $decoded = Base32::decode($secret);
        $counterBytes = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $counterBytes, $decoded, true);
        $offset = ord($hash[-1]) & 0x0f;
        $binary = (unpack('N', substr($hash, $offset, 4))[1] ?? 0) & 0x7fffffff;

        return $binary % (10 ** self::DIGITS);
    }
}

final class Base32
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function encode(string $data): string
    {
        $encoded = '';
        $length = strlen($data);
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < $length; $i++) {
            $value = ($value << 8) | ord($data[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $encoded .= self::ALPHABET[($value >> ($bits - 5)) & 31];
                $bits -= 5;
            }
        }

        return $encoded;
    }

    public static function decode(string $data): string
    {
        $data = strtoupper(str_replace('=', '', $data));
        $decoded = '';
        $length = strlen($data);
        $bits = 0;
        $value = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $data[$i];
            $pos = strpos(self::ALPHABET, $char);

            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 character: ' . $char);
            }

            $value = ($value << 5) | $pos;
            $bits += 5;

            while ($bits >= 8) {
                $decoded .= chr(($value >> ($bits - 8)) & 255);
                $bits -= 8;
            }
        }

        return $decoded;
    }
}
