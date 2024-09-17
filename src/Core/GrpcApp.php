<?php

namespace TondbadSwoole\Core;

use co;
use OpenSwoole\Constant;
use OpenSwoole\GRPC\Middleware\LoggingMiddleware;
use OpenSwoole\GRPC\Middleware\TraceMiddleware;
use OpenSwoole\GRPC\Server;
use OpenSwoole\Runtime;

class GrpcApp
{
    protected Server $server;
    protected int $port;

    // Store registered gRPC services
    protected array $services = [];

    /**
     * GrpcApp constructor initializes the gRPC server.
     *
     * @param int|null $port
     */
    public function __construct()
    {
        $this->port = (int) Config::get('GRPC_PORT', 8080);
        $this->initServer();
    }

    /**
     * Initialize the gRPC server.
     */
    protected function initServer()
    {
        // Enable coroutine hooks
        co::set(['hook_flags' => Runtime::HOOK_ALL]);

        // Create the server on the given port
        $this->server = new Server('0.0.0.0', $this->port);

        // Register worker context
        $this->server->withWorkerContext('worker_start_time', function () {
            return time();
        });

        // Add middlewares for logging and tracing
        $this->server->addMiddleware(new LoggingMiddleware());
        $this->server->addMiddleware(new TraceMiddleware());
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Register a gRPC service.
     *
     * @param string $serviceClass
     */
    public function registerService(string $serviceClass)
    {
        $this->server->register($serviceClass);
    }

    /**
     * Start the gRPC server and begin accepting connections.
     */
    public function start()
    {
        echo "gRPC Server started on port {$this->port}\n";

        // Set additional server options
        $this->server->set([
            'log_level' => Constant::LOG_INFO,
        ]);

        // Start the gRPC server
        $this->server->start();
    }
}
