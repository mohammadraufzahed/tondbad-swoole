<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

final class LiveComponentController
{
    public function __construct(
        private readonly LiveComponentManager $manager,
        private readonly ?SseConnectionManager $sse = null,
    ) {
    }

    public function handle(Request $request, Response $response, string $component): void
    {
        try {
            $output = $this->manager->update($component, $request->all());
        } catch (\InvalidArgumentException $e) {
            $response->status(404)->end('Live component not found');

            return;
        } catch (\Throwable $e) {
            $response->status(500)->end('Live update failed');

            return;
        }

        $this->sse?->broadcast($component, $output->html);

        $response->html($output->html);
    }
}
