<?php

declare(strict_types=1);

namespace TondbadSwoole\Grpc;

use TondbadSwoole\Core\Container;

final class ServerBuilder
{
    private string $host = '0.0.0.0';

    private int $port = 9502;

    private int $mode;

    private int $sockType;

    /** @var class-string<BindableService>[] */
    private array $services = [];

    /** @var class-string<ServerInterceptor>[] */
    private array $interceptors = [];

    private array $settings = [
        'enable_coroutine' => true,
    ];

    private bool $reflection = false;

    private bool $health = false;

    public function __construct(
        private readonly Container $container,
    ) {
        $this->mode = defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0;
        $this->sockType = defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0;
    }

    public function forPort(string $host, int $port, ?Container $container = null): self
    {
        $clone = new self($container ?? $this->container);
        $clone->host = $host;
        $clone->port = $port;

        return $clone;
    }

    public function withContainer(Container $container): self
    {
        $this->container = $container;

        return $this;
    }

    public function mode(int $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function sockType(int $sockType): self
    {
        $this->sockType = $sockType;

        return $this;
    }

    public function maxReceiveMessageSize(int $bytes): self
    {
        $this->settings['package_max_length'] = $bytes;

        return $this;
    }

    public function maxSendMessageSize(int $bytes): self
    {
        $this->settings['package_max_length'] = max($this->settings['package_max_length'] ?? $bytes, $bytes);

        return $this;
    }

    public function keepaliveTimeout(int $seconds): self
    {
        $this->settings['keepalive_timeout'] = $seconds;

        return $this;
    }

    public function set(array $settings): self
    {
        $this->settings = array_merge($this->settings, $settings);

        return $this;
    }

    /** @param class-string<BindableService> $serviceClass */
    public function addService(string $serviceClass): self
    {
        $this->services[] = $serviceClass;

        return $this;
    }

    /** @param class-string<ServerInterceptor> $interceptorClass */
    public function addInterceptor(string $interceptorClass): self
    {
        $this->interceptors[] = $interceptorClass;

        return $this;
    }

    public function enableReflection(): self
    {
        $this->reflection = true;

        return $this;
    }

    public function enableHealth(): self
    {
        $this->health = true;

        return $this;
    }

    public function build(): GrpcServer
    {
        $services = $this->services;

        if ($this->reflection) {
            $services[] = \TondbadSwoole\Grpc\Reflection\V1alpha\ReflectionService::class;
        }

        if ($this->health) {
            $services[] = \TondbadSwoole\Grpc\Health\V1\HealthService::class;
        }

        return new GrpcServer(
            $this->container,
            $services,
            $this->interceptors,
            $this->host,
            $this->port,
            $this->mode,
            $this->sockType,
            $this->settings,
        );
    }
}
