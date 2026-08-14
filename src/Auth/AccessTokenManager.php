<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth;

use TondbadSwoole\Auth\Session\AccessToken;
use TondbadSwoole\Auth\Session\Session;
use TondbadSwoole\Core\Config;

class AccessTokenManager
{
    private string $secret;

    public function __construct(private readonly Config $config)
    {
        $this->secret = (string) $config->get('app.key', '');
    }

    private function secret(): string
    {
        if ($this->secret === '') {
            throw new \RuntimeException('Application key is required for signing access tokens.');
        }

        return $this->secret;
    }

    public function create(Session $session): AccessToken
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'sid' => $session->id,
            'sub' => $session->userId,
            'iat' => time(),
            'exp' => $session->expiresAt,
            'claims' => $session->claims,
            'jti' => bin2hex(random_bytes(16)),
            'csrf' => $session->antiCsrf,
            'mode' => $session->mode,
            'fam' => $session->family,
        ], JSON_THROW_ON_ERROR));

        $value = $header . '.' . $payload . '.' . $this->base64UrlEncode($this->sign($header, $payload));

        return new AccessToken($value, $session->id, $session->expiresAt, $session->claims);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verify(string $value): ?array
    {
        $parts = explode('.', $value);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $decodedSignature = $this->base64UrlDecode($signature);

        if (!hash_equals($this->sign($header, $payload), $decodedSignature)) {
            return null;
        }

        $data = json_decode($this->base64UrlDecode($payload), true);

        if (!is_array($data) || !isset($data['exp']) || $data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    private function sign(string $header, string $payload): string
    {
        return hash_hmac('sha256', $header . '.' . $payload, $this->secret(), true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
