<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use Google\Protobuf\Internal\Message;
use OpenSwoole\GRPC\Client as OpenSwooleClient;

/**
 * @template T of Message
 * @implements Stream<T>
 */
final class ServerStream implements Stream
{
    /** @var class-string<T> */
    private string $responseClass;

    private bool $closed = false;

    /**
     * @param class-string<T> $responseClass
     */
    public function __construct(
        private readonly OpenSwooleClient $client,
        private readonly int $streamId,
        string $responseClass,
    ) {
        $this->responseClass = $responseClass;
    }

    public function recv(): ?object
    {
        if ($this->closed) {
            return null;
        }

        [$data, $trailers] = $this->client->recv($this->streamId);

        if ($trailers !== null) {
            $status = (int) ($trailers['grpc-status'] ?? '0');

            if ($status !== 0) {
                $this->closed = true;
                throw new StatusException(new Status($status, $trailers['grpc-message'] ?? ''));
            }
        }

        if ($data === null || $data === '') {
            $this->closed = true;

            return null;
        }

        /** @var T $message */
        $message = new $this->responseClass();
        $message->mergeFromString($data);

        return $message;
    }

    public function send(object $message): void
    {
        throw new StatusException(Status::unimplemented('Cannot send on a server stream'));
    }

    public function close(?Status $status = null): void
    {
        $this->closed = true;
    }
}
