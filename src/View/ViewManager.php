<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

use TondbadSwoole\Core\Config;
use TondbadSwoole\View\Compilers\TemplateCompiler;

final class ViewManager
{
    /** @var list<string> */
    private array $paths;

    /** @var list<string> */
    private array $componentPaths;

    private string $compiledPath;

    private bool $cacheEnabled;

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, callable> */
    private array $composers = [];

    private ComponentRegistry $registry;

    private TemplateCompiler $compiler;

    public function __construct(
        ?Config $config = null,
        array $paths = [],
        string $compiledPath = '',
        array $componentPaths = [],
    ) {
        $this->paths = $paths ?: [base_path() . '/resources/views'];
        $this->componentPaths = $componentPaths ?: [base_path() . '/app/View/Components'];
        $this->compiledPath = $compiledPath ?: base_path() . '/storage/cache/views';
        $this->cacheEnabled = (bool) ($config?->get('view.cache_enabled', true));
        $this->registry = new ComponentRegistry();
        $this->registry->discover($this->componentPaths);

        $this->compiler = new TemplateCompiler($this);

        if (!is_dir($this->compiledPath)) {
            @mkdir($this->compiledPath, 0755, true);
        }
    }

    public function addPath(string $path): self
    {
        $this->paths[] = $path;

        return $this;
    }

    public function addComponentPath(string $path): self
    {
        $this->componentPaths[] = $path;

        return $this;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function composer(string $view, callable $callback): void
    {
        $this->composers[$view][] = $callback;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        $ctx = new ViewContext($this, $this->shared);

        return $this->renderWithContext($view, $data, $ctx);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderRaw(string $view, array $data, ViewContext $ctx): string
    {
        return $this->renderWithContext($view, $data, $ctx);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderWithContext(string $view, array $data, ViewContext $ctx): string
    {
        $view = $this->normalize($view);

        foreach ($this->composers[$view] ?? [] as $composer) {
            $composer($this->makeViewValueObject($view, $data));
        }

        $compiled = $this->getCompiled($view);

        $output = $compiled->render($data, $ctx);

        if ($ctx->hasLayout()) {
            return $ctx->renderFinal($data);
        }

        return $output;
    }

    public function getCompiled(string $view): AbstractCompiledView
    {
        $source = $this->find($view);
        $className = $this->className($source);
        $compiledFile = $this->compiledPath . '/' . $className . '.php';

        if ($this->needsCompile($source, $compiledFile)) {
            $this->compile($source, $compiledFile);
        }

        require_once $compiledFile;

        $fqcn = 'TondbadSwoole\\View\\Compiled\\' . $className;

        return new $fqcn();
    }

    public function compile(string $source, string $destination): void
    {
        $className = $this->className($source);
        $code = $this->compiler->compile(file_get_contents($source) ?: '', $className);

        $dir = dirname($destination);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($destination, $code, LOCK_EX);
    }

    public function precompile(): void
    {
        foreach (array_merge($this->paths, $this->componentPaths) as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php' || !str_ends_with($file->getBasename(), '.tond.php')) {
                    continue;
                }

                $source = $file->getPathname();
                $this->compile($source, $this->compiledPath . '/' . $this->className($source) . '.php');
            }
        }
    }

    public function compileAll(): void
    {
        $this->precompile();
    }

    public function clearCompiled(): void
    {
        foreach (glob($this->compiledPath . '/*.php') as $file) {
            @unlink((string) $file);
        }
    }

    public function find(string $view): string
    {
        $relative = str_replace('.', '/', $view) . '.tond.php';

        foreach ($this->paths as $path) {
            $candidate = $path . '/' . $relative;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $direct = $view . '.tond.php';
        foreach ($this->paths as $path) {
            if (is_file($path . '/' . $direct)) {
                return $path . '/' . $direct;
            }
        }

        throw new ViewNotFoundException("View [{$view}] not found.");
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, \Closure> $slots
     */
    public function renderComponent(string $name, array $data, array $slots = []): string
    {
        $resolved = $this->registry->resolve($name);

        if ($resolved !== null && class_exists($resolved) && is_subclass_of($resolved, Component::class)) {
            $component = $resolved::create($data)->withSlots($slots);
            $output = $component->render();

            return $output instanceof View ? $output->render() : (string) $output;
        }

        $view = 'components.' . $name;

        try {
            $ctx = new ViewContext($this, $this->shared);
            $data = array_merge(['slot' => $slots['default'] ?? fn () => ''], $data);

            foreach ($slots as $slotName => $closure) {
                $data[$slotName] = $closure;
            }

            return $this->renderWithContext($view, $data, $ctx);
        } catch (ViewNotFoundException) {
            throw new ViewNotFoundException("Component [{$name}] not found.");
        }
    }

    public function renderLiveComponent(string $name, array $data = []): string
    {
        return 'Live:' . $name;
    }

    public function registerComponent(string $name, string $classOrView): void
    {
        $this->registry->register($name, $classOrView);
    }

    public function registry(): ComponentRegistry
    {
        return $this->registry;
    }

    public function normalize(string $view): string
    {
        return str_replace('/', '.', $view);
    }

    private function className(string $path): string
    {
        return '__View_' . md5($path);
    }

    private function needsCompile(string $source, string $compiled): bool
    {
        if (!$this->cacheEnabled) {
            return true;
        }

        if (!is_file($compiled)) {
            return true;
        }

        return filemtime($source) > filemtime($compiled);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function makeViewValueObject(string $view, array $data): View
    {
        return new View($this, $view, $data);
    }
}
