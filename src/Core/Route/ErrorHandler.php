<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Route;

use Monolog\Logger;
use OpenSwoole\Http\Response;
use Throwable;
use TondbadSwoole\Core\Config;

class ErrorHandler
{
    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function handle(Throwable $e, Response $response): void
    {
        $this->logger->error($e->getMessage(), ['exception' => $e]);

        $isDebug = $this->config->get('app.debug', false);

        $response->status(500);
        $response->end($isDebug ? '500 Internal Server Error: ' . $e->getMessage() : '500 Internal Server Error');
    }
}
