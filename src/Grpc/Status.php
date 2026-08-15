<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use OpenSwoole\GRPC\Status as OpenSwooleStatus;

final class Status
{
    public function __construct(
        public readonly int $code,
        public readonly string $message = '',
        public readonly ?\Throwable $previous = null,
    ) {
    }

    public static function ok(): self
    {
        return new self(OpenSwooleStatus::OK);
    }

    public static function cancelled(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::CANCELLED, $message, $previous);
    }

    public static function unknown(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::UNKNOWN, $message, $previous);
    }

    public static function invalidArgument(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::INVALID_ARGUMENT, $message, $previous);
    }

    public static function deadlineExceeded(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::DEADLINE_EXCEEDED, $message, $previous);
    }

    public static function notFound(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::NOT_FOUND, $message, $previous);
    }

    public static function alreadyExists(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::ALREADY_EXISTS, $message, $previous);
    }

    public static function permissionDenied(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::PERMISSION_DENIED, $message, $previous);
    }

    public static function resourceExhausted(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::RESOURCE_EXHAUSTED, $message, $previous);
    }

    public static function failedPrecondition(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::FAILED_PRECONDITION, $message, $previous);
    }

    public static function aborted(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::ABORTED, $message, $previous);
    }

    public static function outOfRange(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::OUT_OF_RANGE, $message, $previous);
    }

    public static function unimplemented(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::UNIMPLEMENTED, $message, $previous);
    }

    public static function internal(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::INTERNAL, $message, $previous);
    }

    public static function unavailable(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::UNAVAILABLE, $message, $previous);
    }

    public static function dataLoss(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::DATA_LOSS, $message, $previous);
    }

    public static function unauthenticated(string $message = '', ?\Throwable $previous = null): self
    {
        return new self(OpenSwooleStatus::UNAUTHENTICATED, $message, $previous);
    }

    public function isOk(): bool
    {
        return $this->code === OpenSwooleStatus::OK;
    }
}
