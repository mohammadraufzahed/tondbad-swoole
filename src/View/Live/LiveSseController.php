<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

final class LiveSseController
{
    public function __construct(private readonly SseConnectionManager $manager)
    {
    }

    public function handle(Request $request, Response $response): void
    {
        $component = (string) ($request->query('component') ?? '');

        if ($component === '') {
            $response->status(400)->end('Missing component');

            return;
        }

        $this->manager->subscribe($component, $response->getSwooleResponse());
    }
}
