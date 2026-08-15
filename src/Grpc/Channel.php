<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use Google\Protobuf\Internal\Message;
use OpenSwoole\Coroutine;
use OpenSwoole\GRPC\Client as OpenSwooleClient;
use OpenSwoole\GRPC\Exception\GRPCException;

final class Channel
{
    /** @var array<int, OpenSwooleClient> */
    private array $clients = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly array $options = [],
    ) {
    }

    /**
     * @template T of Message
     * @param class-string<T> $responseClass
     * @return T
     */
    public function invoke(string $method, Message $request, string $responseClass, array $metadata = []): object
    {
        $client = $this->client();
        $streamId = $client->send($method, $request, 'proto');

        if ($streamId === false || $streamId <= 0) {
            throw new StatusException(Status::unavailable('Failed to send gRPC request'));
        }

        [$data, $trailers] = $client->recv($streamId);

        $this->checkStatus($trailers);

        return $this->deserialize($data, $responseClass);
    }

    /**
     * @template T of Message
     * @param class-string<T> $responseClass
     * @return Stream<T>
     */
    public function stream(string $method, Message $request, string $responseClass, array $metadata = []): Stream
    {
        $client = $this->client();
        $streamId = $client->send($method, $request, 'proto');

        if ($streamId === false || $streamId <= 0) {
            throw new StatusException(Status::unavailable('Failed to send gRPC request'));
        }

        return new ServerStream($client, $streamId, $responseClass);
    }

    private function client(): OpenSwooleClient
    {
        $cid = Coroutine::getCid();

        if (!isset($this->clients[$cid])) {
            $client = new OpenSwooleClient($this->host, $this->port);
            $client->set($this->options);

            if (!$client->connect()) {
                throw new StatusException(Status::unavailable("Could not connect to {$this->host}:{$this->port}"));
            }

            $this->clients[$cid] = $client;
        }

        return $this->clients[$cid];
    }

    /**
     * @param array<string, string>|null $trailers
     */
    private function checkStatus(?array $trailers): void
    {
        if ($trailers === null) {
            return;
        }

        $status = (int) ($trailers['grpc-status'] ?? '0');

        if ($status !== 0) {
            throw new StatusException(new Status($status, $trailers['grpc-message'] ?? ''));
        }
    }

    /**
     * @template T of Message
     * @param class-string<T> $class
     * @return T
     */
    private function deserialize(?string $data, string $class): object
    {
        /** @var T $message */
        $message = new $class();

        if ($data !== null && $data !== '') {
            $message->mergeFromString($data);
        }

        return $message;
    }
}
