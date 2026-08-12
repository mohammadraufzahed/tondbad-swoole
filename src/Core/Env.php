<?php

declare(strict_types=1);

namespace TondbadSwoole\Core;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use RuntimeException;

class Env
{
    /**
     * @var array<string, mixed>
     */
    private array $envCache = [];

    /**
     * @var list<string>
     */
    private array $loadedFiles = [];

    /**
     * @param list<string> $paths
     */
    public function load(array $paths, string $filename = '.env'): void
    {
        foreach ($paths as $path) {
            $filePath = "{$path}/{$filename}";

            if (file_exists($filePath) && !in_array($filePath, $this->loadedFiles, true)) {
                try {
                    $dotenv = Dotenv::createImmutable($path, $filename);
                    $dotenv->load();
                    $this->loadedFiles[] = $filePath;
                    $this->envCache = array_merge($this->envCache, $_ENV, $_SERVER);
                } catch (InvalidPathException $e) {
                    throw new RuntimeException("Environment file not found: {$filePath}");
                }
            }
        }
    }

    /**
     * Load all environment variables from the project root.
     *
     * @param list<string> $paths
     */
    public function loadAll(array $paths = []): void
    {
        $defaultPaths = [
            __DIR__ . '/../..',
        ];

        $this->load(array_merge($defaultPaths, $paths));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $envKey = $this->convertDotNotationToEnvKey($key);

        if (isset($this->envCache[$envKey])) {
            return $this->parseValue($this->envCache[$envKey]);
        }

        if (isset($_ENV[$envKey])) {
            return $this->parseValue($_ENV[$envKey]);
        }

        if (isset($_SERVER[$envKey])) {
            return $this->parseValue($_SERVER[$envKey]);
        }

        $value = getenv($envKey);

        return $value !== false ? $this->parseValue($value) : $default;
    }

    public function has(string $key): bool
    {
        $envKey = $this->convertDotNotationToEnvKey($key);

        return isset($this->envCache[$envKey])
            || isset($_ENV[$envKey])
            || isset($_SERVER[$envKey])
            || getenv($envKey) !== false;
    }

    private function convertDotNotationToEnvKey(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }

    private function parseValue(string $value): mixed
    {
        if ($value === '') {
            return '';
        }

        $lower = strtolower($value);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') === false ? (int) $value : (float) $value;
        }

        return $value;
    }
}
