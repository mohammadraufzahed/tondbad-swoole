<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use Google\Protobuf\Internal\Message;
use OpenSwoole\Coroutine;
use OpenSwoole\Coroutine\Http2\Client as Http2Client;
use OpenSwoole\Http2\Request;

final class Channel
{
    private const DEFAULT_TIMEOUT = 30;

    /** @var array<int, Http2Client> */
    private array $clients = [];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly array $options = [],
    ) {
    }

    /**
     * @template T of Message
     *
     * @param class-string<T> $responseClass
     *
     * @return T
     */
    public function invoke(string $method, Message $request, string $responseClass, array $metadata = []): object
    {
        $client = $this->client();
        $contentType = $this->contentType($metadata);
        $payload = Frame::encode($request, $contentType);
        $streamId = $this->send($client, $method, $payload, $metadata, false);

        $response = $client->recv($this->timeout());

        if (!$response || $response->streamId !== $streamId) {
            throw new StatusException(Status::unavailable('Failed to receive gRPC response'));
        }

        $this->checkStatus($response->headers);

        $messages = [];

        if ($response->data !== null && $response->data !== '') {
            /** @var list<T> $messages */
            $messages = iterator_to_array(Frame::decode($response->data, $responseClass), false);
        }

        return $messages[0] ?? new $responseClass();
    }

    /**
     * @template T of Message
     *
     * @param class-string<T> $responseClass
     *
     * @return Stream<T>
     */
    public function stream(string $method, Message $request, string $responseClass, array $metadata = []): Stream
    {
        $client = $this->client();
        $contentType = $this->contentType($metadata);
        $payload = Frame::encode($request, $contentType);
        $streamId = $this->send($client, $method, $payload, $metadata, false);

        return new ServerStream($client, $streamId, $responseClass, $contentType);
    }

    /**
     * @template T of Message
     *
     * @param class-string<T> $responseClass
     */
    public function clientStream(string $method, string $responseClass, array $metadata = []): ClientStream
    {
        $client = $this->client();
        $contentType = $this->contentType($metadata);
        $streamId = $this->send($client, $method, '', $metadata, true);

        return new ClientStream($client, $streamId, $responseClass, $contentType);
    }

    private function client(): Http2Client
    {
        $cid = Coroutine::getCid();

        if (!isset($this->clients[$cid])) {
            $client = new Http2Client($this->host, $this->port);
            $client->set(array_merge(['timeout' => self::DEFAULT_TIMEOUT], $this->options));

            if (!$client->connect()) {
                throw new StatusException(Status::unavailable("Could not connect to {$this->host}:{$this->port}"));
            }

            $this->clients[$cid] = $client;
        }

        return $this->clients[$cid];
    }

    private function send(Http2Client $client, string $method, string $payload, array $metadata, bool $pipeline): int
    {
        $request = new Request();
        $request->path = $method;
        $request->method = 'POST';
        $request->headers = $this->buildHeaders($metadata);
        $request->data = $payload;
        $request->pipeline = $pipeline;

        $streamId = $client->send($request);

        if ($streamId === false || $streamId <= 0) {
            throw new StatusException(Status::unavailable('Failed to send gRPC request'));
        }

        return $streamId;
    }

    /** @return array<string, string> */
    private function buildHeaders(array $metadata): array
    {
        $headers = array_merge(
            [
                'user-agent' => 'tondbad-grpc/1.0',
                'content-type' => 'application/grpc',
                'te' => 'trailers',
            ],
            $metadata,
        );

        return array_map(static fn ($value) => (string) $value, $headers);
    }

    private function contentType(array $metadata): string
    {
        foreach ($metadata as $key => $value) {
            if (strtolower((string) $key) === 'content-type') {
                $value = (string) $value;

                return in_array($value, ['application/grpc', 'application/grpc+proto', 'application/grpc+json'], true)
                    ? $value
                    : 'application/grpc';
            }
        }

        return 'application/grpc';
    }

    /**
     * @template T of Message
     *
     * @param class-string<T> $class
     *
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

    /**
     * @param array<string, mixed> $trailers
     */
    private function checkStatus(array $trailers): void
    {
        $status = (int) ($trailers['grpc-status'] ?? '0');

        if ($status !== 0) {
            throw new StatusException(new Status($status, (string) ($trailers['grpc-message'] ?? '')));
        }
    }

    private function timeout(): float
    {
        return (float) ($this->options['timeout'] ?? self::DEFAULT_TIMEOUT);
    }
}
