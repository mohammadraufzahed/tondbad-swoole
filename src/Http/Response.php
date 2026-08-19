<?php

declare(strict_types=1);

namespace TondbadSwoole\Http;

use OpenSwoole\Http\Response as SwooleResponse;

class Response
{
    public function __construct(private readonly SwooleResponse $response)
    {
    }

    public function getSwooleResponse(): SwooleResponse
    {
        return $this->response;
    }

    public function status(int $status): self
    {
        @$this->response->status($status);

        return $this;
    }

    public function header(string $key, string $value): self
    {
        $this->response->header($key, $value);

        return $this;
    }

    public function write(string $content): self
    {
        $this->response->write($content);

        return $this;
    }

    public function end(?string $content = null): void
    {
        @$this->response->end($content);
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->response->redirect($url, $status);
        $this->response->end();
    }

    public function json(mixed $data, int $status = 200): void
    {
        $this->status($status)
            ->header('Content-Type', 'application/json')
            ->end(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function html(string $html, int $status = 200): void
    {
        $this->status($status)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->end($html);
    }

    public function view(string $view, array $data = [], int $status = 200): void
    {
        $manager = \app()?->container->make(\TondbadSwoole\View\ViewManager::class);

        $this->html($manager ? $manager->render($view, $data) : '', $status);
    }

    public function text(string $text, int $status = 200): void
    {
        $this->status($status)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->end($text);
    }

    public function error(string $message, int $status = 500): void
    {
        $this->status($status)
            ->header('Content-Type', 'application/json')
            ->end(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
    }

    public function file(string $path, string $contentType = 'application/octet-stream'): void
    {
        if (!is_file($path)) {
            $this->status(404)->end('Not found');

            return;
        }

        $this->status(200)
            ->header('Content-Type', $contentType)
            ->end((string) file_get_contents($path));
    }

    public function cookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = 'lax',
        int $priority = 0,
    ): self {
        $this->response->setCookie($name, $value, $expires, $path, $domain ?? '', $secure, $httpOnly, $sameSite, (string) $priority);

        return $this;
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->response->$method(...$arguments);
    }
}
