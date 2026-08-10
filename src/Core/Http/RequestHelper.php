<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Http;

use OpenSwoole\Http\Request;

class RequestHelper
{
    public static function json(Request $request): ?array
    {
        $raw = $request->rawContent();

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

    public static function query(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->get[$key] ?? $default;
    }

    public static function post(Request $request, string $key, mixed $default = null): mixed
    {
        return $request->post[$key] ?? $default;
    }

    public static function header(Request $request, string $key, mixed $default = null): mixed
    {
        $key = strtolower($key);

        foreach ($request->header ?? [] as $name => $value) {
            if (strtolower($name) === $key) {
                return $value;
            }
        }

        return $default;
    }
}
