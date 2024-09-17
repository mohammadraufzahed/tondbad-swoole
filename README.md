
# TondbadSwoole

**TondbadSwoole** is a custom PHP framework built on **Swoole** to create high-performance asynchronous web applications. It includes routing, dependency injection, graceful shutdowns, and logging using **Monolog** for both console and file outputs.

## Features

- **Asynchronous HTTP Server** powered by Swoole
- **FastRoute** based routing system
- **Dependency Injection** through a custom container
- **Graceful Shutdown** for handling system signals (SIGTERM, SIGINT)
- **Monolog Integration** for logging to both console and log files

## Requirements

- PHP 8.2 or higher
- Swoole extension
- Composer

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/yourusername/tondbadswoole.git
   cd tondbadswoole
   ```

2. Install dependencies using **Composer**:

   ```bash
   composer install
   ```

3. Make sure **Swoole** is installed:

   ```bash
   pecl install swoole
   ```

4. Configure the environment:
   - By default, the server runs on port `8000`. You can configure the port by setting the `PORT` environment variable in a `.env` file.

   Example `.env`:
   ```bash
   PORT=8000
   ```

## Usage

To start the Swoole server, run the following command:

```bash
php public/index.php
```

Once the server starts, it will listen on the configured port (default: `8000`).

### Available Routes

You can define routes in `public/index.php` like this:

```php
$app = TondbadSwoole\Core\App::create();

// Define a GET route
$app->get('/hello/{name}', function (Request $request, Response $response, string $name, ExampleService $exampleService) {
    $greeting = $exampleService->getGreeting($name);
    $response->end($greeting);
});

// Run the application
$app->run();
```

In this example, a simple route `/hello/{name}` is defined, and a greeting message is returned based on the `name` parameter.

### Graceful Shutdown

The server gracefully shuts down when receiving `SIGINT` (`Ctrl+C`) or `SIGTERM` signals. This prevents abrupt termination of the application and allows ongoing requests to complete before stopping the server.

## Logging

This project uses **Monolog** for logging:

- Logs are output to the console (`php://stdout`) and stored in a file (`logs/app.log`).
- You can modify the logging configuration in the `App.php` file.

Example log message when starting the server:

```bash
[2024-09-17 10:34:56] swoole-app.INFO: Starting Swoole server on port: 8000 [] []
```

### Log Levels

The default log level is `DEBUG`, which captures all log levels. You can change the log level by modifying `StreamHandler` in the `setupLogger()` method.

## Contributing

Feel free to submit issues or pull requests to improve this project.

## License

This project is licensed under the MIT License.
