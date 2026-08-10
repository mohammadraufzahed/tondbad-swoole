<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Http;

use OpenSwoole\Http\Response;

class ResponseHelper
{
    public static function json(Response $response, mixed $data, int $status = 200): void
    {
        $response->status($status);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function html(Response $response, string $html, int $status = 200): void
    {
        $response->status($status);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->end($html);
    }

    public static function text(Response $response, string $text, int $status = 200): void
    {
        $response->status($status);
        $response->header('Content-Type', 'text/plain; charset=utf-8');
        $response->end($text);
    }

    public static function redirect(Response $response, string $url, int $status = 302): void
    {
        $response->redirect($url, $status);
        $response->end();
    }

    public static function error(Response $response, string $message, int $status = 500): void
    {
        $response->status($status);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode(['error' => $message], JSON_THROW_ON_ERROR));
    }
}
