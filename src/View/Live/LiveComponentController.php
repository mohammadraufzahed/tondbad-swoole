<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\View\ComponentRegistry;
use TondbadSwoole\View\ViewManager;

final class LiveComponentController
{
    public function __construct(
        private readonly ViewManager $views,
        private readonly StateStore $store,
        private readonly ComponentRegistry $registry,
    ) {
    }

    public function handle(Request $request, Response $response, string $component): void
    {
        $data = $request->all();
        $token = (string) ($data['t:state'] ?? '');
        $state = $token !== '' ? ($this->store->get($token) ?? []) : [];

        $resolved = $this->registry->resolve($component);

        if ($resolved === null || !class_exists($resolved) || !is_subclass_of($resolved, LiveComponent::class)) {
            $response->status(404)->end('Live component not found');

            return;
        }

        $instance = $resolved::create($state);

        if ($instance instanceof LiveComponent) {
            $instance->hydrate($state);
        }

        $instance->mount();

        if ($token !== '') {
            $instance->setStateToken($token, $this->store);
        }

        $this->applyModels($instance, $data);

        $action = (string) ($data['t:action'] ?? '');

        if ($action !== '') {
            $params = (array) ($data['t:params'] ?? []);
            $instance->runAction($action, $params);
        }

        $token = $this->store->save($instance->state());
        $instance->setStateToken($token, $this->store);

        $output = $instance->renderView();
        $output = $this->wrapState($output, $token);

        $response->html($output);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyModels(LiveComponent $instance, array $data): void
    {
        foreach ($data as $key => $value) {
            if (!str_starts_with($key, 't:model:')) {
                continue;
            }

            $property = substr($key, strlen('t:model:'));

            if (property_exists($instance, $property)) {
                $instance->$property = $value;
            }
        }
    }

    private function wrapState(string $html, string $token): string
    {
        $search = '<data-t-state></data-t-state>';

        if (str_contains($html, $search)) {
            return str_replace($search, '<input type="hidden" name="t:state" value="' . e($token) . '">', $html);
        }

        return $html;
    }
}
