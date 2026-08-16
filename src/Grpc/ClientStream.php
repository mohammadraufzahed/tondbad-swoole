<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use Google\Protobuf\Internal\Message;
use OpenSwoole\Coroutine\Http2\Client as Http2Client;

/**
 * @template T of Message
 * @implements Stream<T>
 */
final class ClientStream implements Stream
{
    private bool $writeClosed = false;

    private bool $responseReceived = false;

    /** @var list<T> */
    private array $messages = [];

    private int $index = 0;

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

    /**
     * @return T|null
     */
    public function recv(): ?object
    {
        if (!$this->writeClosed) {
            throw new \RuntimeException('Cannot recv on a client stream until it has been half-closed');
        }

        if (!$this->responseReceived) {
            $this->receiveResponse();
        }

        return $this->messages[$this->index++] ?? null;
    }

    /**
     * @param T $message
     */
    public function send(object $message): void
    {
        if ($this->writeClosed) {
            throw new \RuntimeException('Cannot send on a closed client stream');
        }

        $frame = Frame::encode($message, $this->contentType);

        if (!$this->client->write($this->streamId, $frame, false)) {
            throw new StatusException(Status::unavailable('Failed to write gRPC client stream frame'));
        }
    }

    /**
     * Half-close the client stream and return the first server message.
     *
     * @return T
     */
    public function closeWrite(): ?object
    {
        if ($this->writeClosed) {
            return $this->messages[$this->index] ?? null;
        }

        $this->writeClosed = true;

        if (!$this->client->write($this->streamId, '', true)) {
            throw new StatusException(Status::unavailable('Failed to close gRPC client stream'));
        }

        return $this->recv();
    }

    /**
     * Alias for closeWrite().
     *
     * @return T|null
     */
    public function close(?Status $status = null): ?object
    {
        return $this->closeWrite();
    }

    private function receiveResponse(): void
    {
        $this->responseReceived = true;

        $response = $this->client->recv(30);

        if (!$response || $response->streamId !== $this->streamId) {
            throw new StatusException(Status::unavailable('Failed to receive gRPC response'));
        }

        $grpcStatus = (int) ($response->headers['grpc-status'] ?? '0');

        if ($grpcStatus !== 0) {
            throw new StatusException(new Status($grpcStatus, (string) ($response->headers['grpc-message'] ?? '')));
        }

        if ($response->data !== null && $response->data !== '') {
            /** @var list<T> $messages */
            $messages = iterator_to_array(Frame::decode($response->data, $this->responseClass), false);
            $this->messages = $messages;
        }
    }
}
