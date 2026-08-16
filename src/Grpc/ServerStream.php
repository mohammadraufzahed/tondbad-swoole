<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use Google\Protobuf\Internal\Message;
use OpenSwoole\Coroutine\Http2\Client as Http2Client;

/**
 * @template T of Message
 * @implements Stream<T>
 */
final class ServerStream implements Stream
{
    /** @var list<T> */
    private array $messages = [];

    private int $index = 0;

    private bool $received = false;

    private bool $closed = false;

    /**
     * @param class-string<T> $responseClass
     */
    public function __construct(
        private readonly Http2Client $client,
        private readonly int $streamId,
        private readonly string $responseClass,
        private readonly ?string $contentType = 'application/grpc',
    ) {
    }

    public function recv(): ?object
    {
        if ($this->closed) {
            return null;
        }

        if (!$this->received) {
            $this->receive();
        }

        return $this->messages[$this->index++] ?? null;
    }

    public function send(object $message): void
    {
        throw new StatusException(Status::unimplemented('Cannot send on a server stream'));
    }

    public function close(?Status $status = null): ?object
    {
        $this->closed = true;

        return null;
    }

    private function receive(): void
    {
        $this->received = true;

        $response = $this->client->recv(30);

        if (!$response || $response->streamId !== $this->streamId) {
            $this->closed = true;
            throw new StatusException(Status::unavailable('Failed to receive gRPC stream'));
        }

        $status = (int) ($response->headers['grpc-status'] ?? '0');

        if ($status !== 0) {
            $this->closed = true;
            throw new StatusException(new Status($status, (string) ($response->headers['grpc-message'] ?? '')));
        }

        if ($response->data !== null && $response->data !== '') {
            /** @var list<T> $messages */
            $messages = iterator_to_array(Frame::decode($response->data, $this->responseClass), false);
            $this->messages = $messages;
        }
    }
}
