<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing;

use DateTimeInterface;
use InvalidArgumentException;

class SignedUrl
{
    public function __construct(private readonly string $key)
    {
        if ($key === '') {
            throw new InvalidArgumentException('Signed URL key cannot be empty.');
        }
    }

    public function make(string $url, ?DateTimeInterface $expires = null): string
    {
        $url = $this->appendExpires($url, $expires);
        $signature = $this->signature($url);

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'signature=' . $signature;
    }

    /**
     * @param array<string, string> $query
     */
    public function validate(string $path, array $query, ?DateTimeInterface $now = null): bool
    {
        $signature = $query['signature'] ?? '';

        if (!is_string($signature) || $signature === '') {
            return false;
        }

        $expires = $query['expires'] ?? null;

        if ($expires !== null) {
            $expiresAt = (int) $expires;

            if ($expiresAt < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
                return false;
            }
        }

        unset($query['signature']);

        $expected = $this->signature($this->buildUrl($path, $query));

        return hash_equals($expected, $signature);
    }

    private function appendExpires(string $url, ?DateTimeInterface $expires): string
    {
        if ($expires === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'expires=' . $expires->getTimestamp();
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $parts[] = urlencode((string) $key) . '=' . urlencode((string) $value);
        }

        if ($parts === []) {
            return $path;
        }

        return $path . '?' . implode('&', $parts);
    }

    private function signature(string $url): string
    {
        return hash_hmac('sha256', $url, $this->key);
    }
}
