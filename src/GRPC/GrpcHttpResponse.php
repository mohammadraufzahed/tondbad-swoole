<?php

declare(strict_types=1);

namespace TondbadSwoole\GRPC;

use OpenSwoole\Http\Response as SwooleResponse;

class GrpcHttpResponse extends SwooleResponse
{
    public int $capturedStatus = 200;
    public string $capturedBody = '';

    /** @var array<string, string> */
    public array $capturedHeaders = [];

    public function status(int $statusCode, string $reason = ''): bool
    {
        $this->capturedStatus = $statusCode;

        return true;
    }

    public function header(string $key, string $value, bool $format = true): bool
    {
        $this->capturedHeaders[strtolower($key)] = $value;

        return true;
    }

    public function write(string $data): bool
    {
        $this->capturedBody .= $data;

        return true;
    }

    public function end(?string $data = null): bool
    {
        if ($data !== null) {
            $this->capturedBody .= $data;
        }

        return true;
    }

    public function redirect(string $url, int $status_code = 302): ?bool
    {
        $this->capturedStatus = $status_code;

        return null;
    }
}
