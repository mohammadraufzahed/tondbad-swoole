<?php

declare(strict_types=1);

namespace TondbadSwoole\View;

final class ComponentRegistry
{
    /** @var array<string, class-string<Component>|string> */
    private array $map = [];

    /**
     * @param class-string<Component>|string $classOrView
     */
    public function register(string $name, string $classOrView): void
    {
        $this->map[$name] = $classOrView;
    }

    /**
     * @return class-string<Component>|string|null
     */
    public function resolve(string $name): ?string
    {
        return $this->map[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->map[$name]);
    }

    /**
     * @param list<string> $paths
     */
    public function discover(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach (new \DirectoryIterator($path) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->classNameFromFile($file->getPathname());

                if ($className === null) {
                    continue;
                }

                $name = $this->componentNameFromClass($className);

                $this->register($name, $className);
            }
        }
    }

    private function classNameFromFile(string $path): ?string
    {
        $contents = file_get_contents($path) ?: '';

        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch) !== 1) {
            return null;
        }

        if (preg_match('/class\s+(\S+)\s+extends/', $contents, $classMatch) !== 1) {
            return null;
        }

        return $namespaceMatch[1] . '\\' . $classMatch[1];
    }

    private function componentNameFromClass(string $className): string
    {
        $shortName = substr($className, (int) strrpos($className, '\\') + 1);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));
    }
}
