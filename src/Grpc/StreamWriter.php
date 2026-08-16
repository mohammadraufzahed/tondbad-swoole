<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class StreamWriter
{
    private bool $headersSent = false;

    private bool $closed = false;

    public function __construct(
        private readonly Context $context,
        private readonly ?string $contentType = 'application/grpc',
    ) {
    }

    public function write(object $message): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Cannot write to a closed gRPC stream');
        }

        $this->ensureHeaders();

        $response = $this->getResponse();
        $data = Frame::encode($message, $this->contentType);

        if (!$response->write($data)) {
            throw new \RuntimeException('Failed to write gRPC stream frame');
        }
    }

    public function close(?Status $status = null): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $status ??= Status::ok();

        $this->ensureHeaders();

        $response = $this->getResponse();
        $response->trailer('grpc-status', (string) $status->code);
        $response->trailer('grpc-message', $status->message);
        $response->end('');
    }

    private function ensureHeaders(): void
    {
        if ($this->headersSent) {
            return;
        }

        $response = $this->getResponse();
        $response->header('content-type', $this->contentType ?? 'application/grpc');
        $response->header('trailer', 'grpc-status, grpc-message');

        $this->headersSent = true;
    }

    private function getResponse(): \OpenSwoole\HTTP\Response
    {
        $response = $this->context->getValue(\OpenSwoole\HTTP\Response::class);

        if (!$response instanceof \OpenSwoole\HTTP\Response) {
            throw new \RuntimeException('StreamWriter requires an OpenSwoole HTTP response in the context');
        }

        return $response;
    }
}
