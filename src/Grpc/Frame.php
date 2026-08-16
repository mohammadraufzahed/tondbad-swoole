<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

final class Frame
{
    public static function encode(object $message, ?string $contentType = 'application/grpc'): string
    {
        if (!$message instanceof \Google\Protobuf\Internal\Message) {
            throw new \InvalidArgumentException('gRPC message must be a protobuf Message');
        }

        if ($contentType === 'application/grpc+json') {
            $payload = $message->serializeToJsonString();
        } else {
            $payload = $message->serializeToString();
        }

        return pack('CN', 0, strlen($payload)) . $payload;
    }

    /**
     * @template T of \Google\Protobuf\Internal\Message
     *
     * @param class-string<T> $messageClass
     *
     * @return \Generator<int, T>
     */
    public static function decode(string $payload, string $messageClass): \Generator
    {
        $offset = 0;
        $length = strlen($payload);

        while ($offset < $length) {
            if ($offset + 5 > $length) {
                throw new \InvalidArgumentException('Incomplete gRPC frame header');
            }

            $compressed = ord($payload[$offset]) !== 0;

            if ($compressed) {
                throw new \InvalidArgumentException('Compressed gRPC frames are not supported');
            }

            $frameLength = unpack('N', substr($payload, $offset + 1, 4))[1];

            if ($offset + 5 + $frameLength > $length) {
                throw new \InvalidArgumentException('Incomplete gRPC frame body');
            }

            $frame = substr($payload, $offset + 5, $frameLength);
            $offset += 5 + $frameLength;

            /** @var T $message */
            $message = new $messageClass();

            if ($frame !== '') {
                $message->mergeFromString($frame);
            }

            yield $message;
        }
    }

    /**
     * @template T of \Google\Protobuf\Internal\Message
     *
     * @param class-string<T> $messageClass
     *
     * @return list<T>
     */
    public static function decodeAll(string $payload, string $messageClass): array
    {
        if ($payload === '') {
            return [];
        }

        return iterator_to_array(self::decode($payload, $messageClass), false);
    }
}
