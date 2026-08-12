<?php

declare(strict_types=1);

namespace TondbadSwoole\Http;

use OpenSwoole\Http\Request as SwooleRequest;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Validation\ValidationException;
use TondbadSwoole\Validation\Validator;

class Request
{
    public function __construct(private readonly SwooleRequest $request)
    {
    }

    public function getSwooleRequest(): SwooleRequest
    {
        return $this->request;
    }

    public function fd(): int
    {
        return $this->request->fd;
    }

    public function streamId(): int
    {
        return $this->request->streamId;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);

        foreach ($this->request->header ?? [] as $name => $value) {
            if (strtolower($name) === $key) {
                return $value;
            }
        }

        return $default;
    }

    public function headers(): array
    {
        return $this->request->header ?? [];
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->request->server[$key] ?? $default;
    }

    public function method(): string
    {
        return strtoupper($this->request->server['request_method'] ?? 'GET');
    }

    public function uri(): string
    {
        return $this->request->server['request_uri'] ?? '/';
    }

    public function path(): string
    {
        $uri = $this->uri();

        if (false !== $pos = strpos($uri, '?')) {
            return substr($uri, 0, $pos);
        }

        return $uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->request->get[$key] ?? $default;
    }

    public function queries(): array
    {
        return $this->request->get ?? [];
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->request->post[$key] ?? $default;
    }

    public function posts(): array
    {
        return $this->request->post ?? [];
    }

    public function json(): ?array
    {
        $contentType = $this->header('Content-Type', '');

        if (!str_contains(strtolower($contentType), 'application/json')) {
            return null;
        }

        $raw = @$this->request->rawContent();

        if ($raw === '' || $raw === false || $raw === null) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            return null;
        }
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post($key) ?? $this->query($key) ?? ($this->json()[$key] ?? $default);
    }

    public function all(): array
    {
        return array_merge($this->queries(), $this->posts(), $this->json() ?? []);
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->request->cookie[$key] ?? $default;
    }

    public function cookies(): array
    {
        return $this->request->cookie ?? [];
    }

    public function files(): array
    {
        return $this->request->files ?? [];
    }

    public function rawContent(): string|false
    {
        return $this->request->rawContent();
    }

    public function __get(string $name): mixed
    {
        return $this->request->$name ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->request->$name);
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = []): array
    {
        $manager = function_exists('app') ? app()?->container->make(DatabaseManager::class) : null;

        $validator = new Validator($this->all(), $rules, $messages, $manager);

        return $validator->validated();
    }
}
