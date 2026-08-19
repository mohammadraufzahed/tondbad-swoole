<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as WebSocketServer;
use OpenSwoole\Http\Request;
use OpenSwoole\Server;

final class WsConnectionManager
{
    /**
     * @var array<int, array{component: string, token: string, html: string}>
     */
    private array $connections = [];

    public function __construct(private readonly LiveComponentManager $manager)
    {
    }

    public function onOpen(WebSocketServer $server, Request $request): void
    {
        $fd = (int) $request->fd;

        $this->connections[$fd] = ['component' => '', 'token' => '', 'html' => ''];
    }

    public function onMessage(WebSocketServer $server, Frame $frame): void
    {
        $fd = (int) $frame->fd;
        $payload = json_decode((string) $frame->data, true);

        if (!is_array($payload)) {
            $this->pushError($server, $fd, 'Invalid JSON payload');

            return;
        }

        $connection = $this->connections[$fd] ?? ['component' => '', 'token' => '', 'html' => ''];

        $component = (string) ($payload['t:component'] ?? $connection['component']);
        $payload['t:state'] = $payload['t:state'] ?? $connection['token'];

        if ($component === '') {
            $this->pushError($server, $fd, 'Missing component name');

            return;
        }

        try {
            $result = $this->manager->update($component, $payload);
        } catch (\Throwable $e) {
            $this->pushError($server, $fd, $e->getMessage());

            return;
        }

        $patches = LivePatcher::diff($connection['html'], $result->html);

        $this->connections[$fd] = [
            'component' => $component,
            'token' => $result->token,
            'html' => $result->html,
        ];

        $server->push($fd, json_encode([
            'patches' => $patches,
            'token' => $result->token,
        ], JSON_THROW_ON_ERROR));
    }

    public function onClose(Server $server, int $fd): void
    {
        unset($this->connections[$fd]);
    }

    /**
     * @param array<int, mixed> $connections
     */
    public function setConnections(array $connections): void
    {
        $this->connections = $connections;
    }

    private function pushError(WebSocketServer $server, int $fd, string $message): void
    {
        $server->push($fd, json_encode(['error' => $message], JSON_THROW_ON_ERROR));
    }
}
