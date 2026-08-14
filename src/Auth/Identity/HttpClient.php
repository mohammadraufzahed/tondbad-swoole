<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

interface HttpClient
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $data
     */
    public function post(string $url, array $data, array $headers = []): HttpResponse;

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): HttpResponse;
}
