<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use TondbadSwoole\View\ComponentRegistry;

final class LiveComponentManager
{
    public function __construct(
        private readonly StateStore $store,
        private readonly ComponentRegistry $registry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = []): LiveUpdate
    {
        $instance = $this->resolve($name, $data);

        $instance->mount();
        $instance->hydrate($data);

        return $this->finalize($instance);
    }

    /**
     * @param array<string, mixed> $request
     */
    public function update(string $name, array $request): LiveUpdate
    {
        $token = (string) ($request['t:state'] ?? '');
        $state = $token !== '' ? ($this->store->get($token) ?? []) : [];

        $instance = $this->resolve($name, $state);

        if ($token === '') {
            $instance->mount();
        } else {
            $instance->hydrate($state);
            $instance->setStateToken($token, $this->store);
        }

        $instance->syncInputs($request);

        $action = (string) ($request['t:action'] ?? '');

        if ($action !== '') {
            $params = $request['t:params'] ?? [];

            if (is_string($params) && $params !== '') {
                $decoded = json_decode($params, true);
                $params = is_array($decoded) ? $decoded : [];
            }

            $instance->runAction($action, (array) $params);
        }

        return $this->finalize($instance);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolve(string $name, array $data): LiveComponent
    {
        $resolved = $this->registry->resolve($name);

        if ($resolved === null || !class_exists($resolved) || !is_subclass_of($resolved, LiveComponent::class)) {
            throw new \InvalidArgumentException("Live component [{$name}] not found.");
        }

        /** @var LiveComponent $instance */
        $instance = $resolved::create($data);

        return $instance;
    }

    private function finalize(LiveComponent $instance): LiveUpdate
    {
        $previousToken = $instance->stateToken();
        $html = $instance->renderView();
        $token = $this->store->save($instance->state());

        if ($previousToken !== null && $previousToken !== $token) {
            $this->store->delete($previousToken);
        }

        $instance->setStateToken($token, $this->store);

        return new LiveUpdate(LiveRender::wrap($html, $token), $token);
    }
}
