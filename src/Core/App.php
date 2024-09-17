<?php

namespace TondbadSwoole\Core;

use OpenSwoole\Http\{Request, Response};
use OpenSwoole\WebSocket\Server;
use OpenSwoole\Process;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class App
{
    protected readonly Route $router;
    protected Server $server;
    protected readonly Logger $logger;

    /**
     * App constructor initializes the container and the router.
     */
    public function __construct(
        public readonly Container $container = new Container
    ) {
        $this->setupLogger();
        $this->resolveLogger();
        $this->router = new Route($this->container);
    }

    /**
     * Set up the Monolog logger with a ConsoleHandler for logging to console.
     */
    protected function setupLogger()
    {
        $this->container->singleton(Logger::class, function () {
            $logger = new Logger('swoole-app');

            $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

            $logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));

            return $logger;
        });
    }

    /**
     * Resolve the logger
     */

    protected function resolveLogger()
    {
        $this->logger = $this->container->make(Logger::class);
    }

    /**
     * Create a new App instance.
     *
     * @return static
     */
    public static function create(): self
    {
        return new self();
    }

    public function get(string $path, callable $handler)
    {
        $this->router->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler)
    {
        $this->router->addRoute('POST', $path, $handler);
    }
    public function put(string $path, callable $handler)
    {
        $this->router->addRoute('PUT', $path, $handler);
    }
    public function delete(string $path, callable $handler)
    {
        $this->router->addRoute('DELETE', $path, $handler);
    }
    public function patch(string $path, callable $handler)
    {
        $this->router->addRoute('PATCH', $path, $handler);
    }
    public function options(string $path, callable $handler)
    {
        $this->router->addRoute('OPTIONS', $path, $handler);
    }
    public function addRoute(string $method, string $path, callable $handler)
    {
        $this->router->addRoute($method, $path, $handler);
    }
    /**
     * Gracefully shutdown the server on system signals.
     */
    protected function setupSignalHandlers()
    {
        Process::signal(SIGTERM, function () {
            echo "SIGTERM received, shutting down...\n";
            $this->stopServer();
        });

        Process::signal(SIGINT, function () {
            echo "SIGINT received, shutting down...\n";
            $this->stopServer();
        });
    }

    /**
     * Stop the Swoole server gracefully.
     */
    protected function stopServer()
    {
        if ($this->server->shutdown()) {
            echo "Server shut down gracefully.\n";
        } else {
            echo "Server failed to shut down.\n";
        }
    }


    /**
     * Run the Swoole HTTP server and handle requests.
     */
    public function run()
    {
        $port = (int) Config::get('PORT', 8000);
        $this->server = new Server('0.0.0.0', $port);

        $this->server->on('request', function (Request $request, Response $response) {
            $this->router->dispatch($request, $response);
        });
        $this->server->on('message', function () {});

        // Start the Swoole server
        $this->logger->log('debug', "Server started on port: $port");

        $this->server->start();

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers();
    }

    /**
     * Get the container instance to bind and resolve services.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
}