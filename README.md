# TondbadSwoole

**TondbadSwoole** is a high-performance, lightweight PHP framework built on **OpenSwoole** for creating asynchronous web
applications, microservices, and gRPC servers. It offers a robust routing system, dependency injection, and support for
gRPC, making it an ideal choice for modern PHP applications.

## Features

- Asynchronous HTTP Server powered by OpenSwoole.
- FastRoute based routing system with attribute-based route definitions.
- Dependency Injection using a custom container for managing services and configurations.
- gRPC Support for building high-performance microservices.
- Monolog Integration for comprehensive logging to console and files.
- Graceful Shutdown to handle system signals (SIGTERM, SIGINT) and ensure smooth termination.

## Requirements

- PHP 8.2 or higher
- OpenSwoole extension
- Composer

## Installation

1. **Clone the repository**:

   ```bash
   git clone https://github.com/yourusername/tondbad-swoole.git
   cd tondbad-swoole
   ```

2. **Install dependencies using Composer**:

   ```bash
   composer install
   ```

3. **Install OpenSwoole**:

   ```bash
   pecl install openswoole
   ```

4. **Configure the environment**:
    - Create a `.env` file in the root directory and configure necessary settings. For example:

   ```bash
   PORT=8000
   APP_ENV=local
   APP_DEBUG=true
   ```

## Usage

### Running the HTTP Server

To start the HTTP server, run the following command:

```bash
composer server
```

This will start the OpenSwoole server on the configured port (default: `8000`).

### Running the gRPC Server

To start the gRPC server, use the command:

```bash
composer grpc
```

This will start the gRPC server for handling gRPC requests.

## Defining Routes

Routes can be defined using PHP 8 attributes within your controllers. Here is an example of a route definition using the
`#[Endpoint]` attribute:

```php
namespace App\Controllers;

use TondbadSwoole\Core\Route\Attributes\Endpoint;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

class HomeController
{
    #[Endpoint('GET', '/')]
    public function index(Request $request, Response $response)
    {
        $response->end('Welcome to TondbadSwoole!');
    }

    #[Endpoint('POST', '/submit')]
    public function submit(Request $request, Response $response)
    {
        $response->end('Form Submitted!');
    }
}
```

### Registering Routes

In your configuration file (`config/routes.php`), list the classes containing route definitions:

```php
return [
    \App\Controllers\HomeController::class,
    \App\Controllers\UserController::class,
];
```

## Logging

TondbadSwoole uses **Monolog** for logging:

- Logs are written to the console (`php://stdout`) and stored in a file (`logs/app.log`).
- You can configure the logging settings in the `LoggerServiceProvider`.

### Log Levels

The default log level is `DEBUG`, capturing all levels of logs. You can change this level in the logger configuration.

## Graceful Shutdown

The server will gracefully shut down on receiving `SIGINT` (`Ctrl+C`) or `SIGTERM` signals, allowing ongoing requests to
complete before stopping the server.

## Compilation of Protocol Buffers (for gRPC)

To compile `.proto` files, run the following command:

```bash
composer compile-proto
```

This command will generate the necessary PHP files for gRPC communication.

## Coming Soon

- Background Job Processing: The next version will include support for background job processing using OpenSwoole's
  TaskWorker, allowing you to handle long-running tasks efficiently in the background.
- Queue Management: Enhanced queue management for dispatching and handling background tasks.

## Contributing

Feel free to submit issues or pull requests to improve this project. Contributions are welcome!

## License

This project is licensed under the MIT License.