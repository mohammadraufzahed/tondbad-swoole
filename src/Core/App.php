<?php

namespace TondbadSwoole\Core;

use Swoole\Http\{Request, Response, Server};

class App
{
    protected Route $router;

    /**
     * App constructor initializes the container and the router.
     */
    public function __construct(
        public readonly Container $container = new Container
    ) {
        // Initialize the container and the router
        $this->router = new Route($this->container);
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
        $this->router->get($path, $handler);
    }

    public function post(string $path, callable $handler)
    {
        $this->router->post($path, $handler);
    }

    /**
     * Run the Swoole HTTP server and handle requests.
     */
    public function run()
    {
        $port = (int) Config::get('PORT', 8000);
        $server = new Server('0.0.0.0', $port);

        $server->on('request', function (Request $request, Response $response) {
            $this->router->dispatch($request, $response);
        });

        // Start the Swoole server
        echo "Server started on port: $port\n";
        $server->start();
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