<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use OpenSwoole\HTTP\Response as SwooleResponse;
use OpenSwoole\Server;

final class SseConnectionManager
{
    private ?Server $server = null;

    /**
     * @var array<string, list<SwooleResponse>>
     */
    private array $streams = [];

    /**
     * @param array<string, list<SwooleResponse>> $streams
     */
    public function setStreams(array $streams): void
    {
        $this->streams = $streams;
    }

    public function setServer(Server $server): void
    {
        $this->server = $server;
    }

    public function subscribe(string $component, SwooleResponse $response): void
    {
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');
        $response->header('X-Accel-Buffering', 'no');
        $response->status(200);

        if (!isset($this->streams[$component])) {
            $this->streams[$component] = [];
        }

        $this->streams[$component][] = $response;

        $response->write('data: ' . json_encode(['type' => 'connected', 'component' => $component], JSON_THROW_ON_ERROR) . "\n\n");
    }

    public function broadcast(string $component, string $newHtml): void
    {
        $this->writeToComponent($component, $newHtml);

        if ($this->server === null) {
            return;
        }

        $workerNum = (int) ($this->server->setting['worker_num'] ?? 1);
        $current = $this->server->worker_id;
        $message = json_encode(['t:sse:broadcast', $component, $newHtml], JSON_THROW_ON_ERROR);

        for ($i = 0; $i < $workerNum; ++$i) {
            if ($i === $current) {
                continue;
            }

            $this->server->sendMessage($message, $i);
        }
    }

    public function onPipeMessage(Server $server, int $workerId, mixed $data): void
    {
        $message = json_decode((string) $data, true);

        if (!is_array($message) || count($message) !== 3 || ($message[0] ?? null) !== 't:sse:broadcast') {
            return;
        }

        [, $component, $html] = $message;

        if (!is_string($component) || !is_string($html)) {
            return;
        }

        $this->writeToComponent($component, $html);
    }

    private function writeToComponent(string $component, string $newHtml): void
    {
        $responses = $this->streams[$component] ?? [];

        if ($responses === []) {
            return;
        }

        $payload = 'data: ' . json_encode([
            'patches' => [
                ['type' => 'replace', 'id' => 0, 'html' => $newHtml],
            ],
        ], JSON_THROW_ON_ERROR) . "\n\n";

        $alive = [];

        foreach ($responses as $response) {
            if ($response->write($payload) === false) {
                continue;
            }

            $alive[] = $response;
        }

        $this->streams[$component] = $alive;
    }
}
