<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Identity;

use OpenSwoole\Coroutine\Http\Client;

class OpenSwooleHttpClient implements HttpClient
{
    public function post(string $url, array $data, array $headers = []): HttpResponse
    {
        $parts = $this->parseUrl($url);
        $client = $this->client($parts);
        $client->setHeaders(array_merge($headers, ['Content-Type' => 'application/x-www-form-urlencoded']));

        $body = http_build_query($data);
        $client->post($parts['path'], $body);

        return $this->response($client);
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        $parts = $this->parseUrl($url);
        $client = $this->client($parts);
        $client->setHeaders($headers);
        $client->get($parts['path']);

        return $this->response($client);
    }

    /**
     * @return array{host: string, port: int, ssl: bool, path: string}
     */
    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException('Invalid URL: ' . $url);
        }

        $scheme = $parts['scheme'] ?? 'http';
        $ssl = $scheme === 'https';
        $port = (int) ($parts['port'] ?? ($ssl ? 443 : 80));
        $path = ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return [
            'host' => $parts['host'],
            'port' => $port,
            'ssl' => $ssl,
            'path' => $path,
        ];
    }

    /**
     * @param array{host: string, port: int, ssl: bool, path: string} $parts
     */
    private function client(array $parts): Client
    {
        return new Client($parts['host'], $parts['port'], $parts['ssl']);
    }

    private function response(Client $client): HttpResponse
    {
        $body = $client->getBody() ?? '';
        $status = (int) ($client->statusCode ?? 0);
        $json = json_decode($body, true) ?: [];

        $client->close();

        return new HttpResponse($status, $body, is_array($json) ? $json : []);
    }
}
