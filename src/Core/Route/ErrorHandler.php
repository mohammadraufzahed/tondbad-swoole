<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Monolog\Logger;
use OpenSwoole\Http\Response;
use Throwable;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Validation\ValidationException;

class ErrorHandler
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function handle(Throwable $e, Response $response): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        if ($e instanceof ValidationException) {
            $response->status(422);
            $response->header('Content-Type', 'application/json');
            $response->end(json_encode(['message' => $e->getMessage(), 'errors' => $e->getErrors()], JSON_THROW_ON_ERROR));

            return;
        }

        $isDebug = $this->config->get('app.debug', false);

        $message = '500 Internal Server Error';
        if ($isDebug) {
            $message .= ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        $response->status(500);
        $response->end($message);
    }
}
