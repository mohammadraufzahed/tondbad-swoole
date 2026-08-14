<?php

declare(strict_types=1);

namespace TondbadSwoole\GRPC;

use OpenSwoole\Http\Request as SwooleRequest;

class GrpcHttpRequest extends SwooleRequest
{
    private string $rawContent;

    /**
     * @param array<string, mixed> $server
     * @param array<string, string> $header
     * @param array<string, string> $get
     * @param array<string, mixed> $post
     * @param array<string, string> $cookie
     */
    public function __construct(
        string $rawContent,
        array $server = [],
        array $header = [],
        array $get = [],
        array $post = [],
        array $cookie = []
    ) {
        $this->rawContent = $rawContent;
        $this->server = $server;
        $this->header = $header;
        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
        $this->files = [];
    }

    public function rawContent(): string
    {
        return $this->rawContent;
    }
}
