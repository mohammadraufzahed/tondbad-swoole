<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

use Closure;

final class ViewContext
{
    private ?string $layout = null;

    /** @var array<string, Closure> */
    private array $sections = [];

    /** @var array<string, string> */
    private array $parentSections = [];

    /** @var array<string, list<Closure>> */
    private array $stacks = [];

    /** @var list<array{name: string, data: array, slots: array<string, Closure>}> */
    private array $componentStack = [];

    private bool $captureSections = false;

    public function __construct(
        private readonly ViewManager $manager,
        private readonly array $shared = [],
    ) {
    }

    public function setCaptureMode(bool $capture): void
    {
        $this->captureSections = $capture;
    }

    public function isCaptureMode(): bool
    {
        return $this->captureSections;
    }

    public function layout(string $name): void
    {
        $this->layout = $name;
    }

    public function hasLayout(): bool
    {
        return $this->layout !== null;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function section(string $name, Closure $content): void
    {
        $this->sections[$name] = $content;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    public function yieldSection(string $name, mixed $default = null): string
    {
        if ($this->captureSections) {
            return '';
        }

        if (isset($this->sections[$name])) {
            return (string) ($this->sections[$name])($this);
        }

        return (string) ($default ?? '');
    }

    public function parent(string $name): string
    {
        return $this->parentSections[$name] ?? '';
    }

    /**
     * @param array<string, string> $parentSections
     */
    public function setParentSections(array $parentSections): void
    {
        $this->parentSections = $parentSections;
    }

    public function push(string $stack, Closure $content): void
    {
        $this->stacks[$stack][] = $content;
    }

    public function stack(string $stack, string $default = ''): string
    {
        if ($this->captureSections) {
            return '';
        }

        if (!isset($this->stacks[$stack])) {
            return $default;
        }

        $parts = [];
        foreach ($this->stacks[$stack] as $content) {
            $parts[] = $content($this);
        }

        return implode('', $parts);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function include(string $view, array $data = []): void
    {
        echo $this->manager->render($view, array_merge($this->shared, $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function startComponent(string $name, array $data = []): void
    {
        $this->componentStack[] = ['name' => $name, 'data' => $data, 'slots' => []];
    }

    public function slot(string $name, Closure $content): void
    {
        $lastIndex = count($this->componentStack) - 1;

        if ($lastIndex < 0) {
            return;
        }

        $this->componentStack[$lastIndex]['slots'][$name] = $content;
    }

    public function endComponent(): void
    {
        $frame = array_pop($this->componentStack);

        if ($frame === null) {
            return;
        }

        echo $this->manager->renderComponent($frame['name'], $frame['data'], $frame['slots']);
    }

    public function getManager(): ViewManager
    {
        return $this->manager;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderFinal(array $data): string
    {
        if ($this->layout === null) {
            return '';
        }

        $parentCtx = new ViewContext($this->manager, $this->shared);
        $parentCtx->setCaptureMode(true);
        $this->manager->renderRaw($this->layout, $data, $parentCtx);
        $this->setParentSections($this->resolveSections($parentCtx));

        $outputCtx = new ViewContext($this->manager, $this->shared);
        $outputCtx->sections = $this->sections;
        $outputCtx->stacks = $this->stacks;
        $outputCtx->setParentSections($this->parentSections);

        return $this->manager->renderRaw($this->layout, $data, $outputCtx);
    }

    /**
     * @return array<string, string>
     */
    private function resolveSections(ViewContext $ctx): array
    {
        $resolved = [];
        foreach ($ctx->sections as $name => $closure) {
            $resolved[$name] = (string) $closure($ctx);
        }

        return $resolved;
    }
}
