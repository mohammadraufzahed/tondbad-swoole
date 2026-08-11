<?php

declare(strict_types=1);

namespace TondbadSwoole\Core;

class Config
{
    /**
     * @var list<string>
     */
    private array $searchPaths = [];

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * @var list<string>
     */
    private array $loadedFiles = [];

    public function __construct(
        private readonly Env $env,
        private string $basePath = '',
        array $searchPaths = []
    ) {
        if ($this->basePath === '') {
            $this->basePath = dirname(__DIR__, 2);
        }

        $this->searchPaths = $searchPaths;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = $segments[0];

        if (!in_array($file, $this->loadedFiles, true)) {
            $this->load($file);
        }

        if ($this->env->has($key)) {
            return $this->env->get($key);
        }

        return $this->getFromArray($key, $this->config, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $file = $segments[0];

        if (!in_array($file, $this->loadedFiles, true)) {
            $this->load($file);
        }

        $this->setInArray($key, $value, $this->config);
    }

    /**
     * @param list<string> $paths
     */
    public function setSearchPaths(array $paths): void
    {
        $this->searchPaths = $paths;
        $this->config = [];
        $this->loadedFiles = [];
    }

    public function addToSearchPaths(string $path): void
    {
        if (!in_array($path, $this->searchPaths, true)) {
            $this->searchPaths[] = $path;
        }
    }

    private function load(string $file): void
    {
        $configs = [];
        $env = $this->env;
        $basePath = $this->basePath;

        foreach (array_unique($this->getSearchPaths()) as $path) {
            $configPath = $path . "/{$file}.php";
            $configs[] = file_exists($configPath) ? require $configPath : [];
        }

        $merged = [];

        foreach ($configs as $config) {
            $merged = array_merge($merged, $config);
        }

        $this->config[$file] = $merged;
        $this->loadedFiles[] = $file;
    }

    /**
     * @return list<string>
     */
    private function getSearchPaths(): array
    {
        $defaults = [
            dirname(__DIR__, 2) . '/config',
        ];

        return array_merge($defaults, $this->searchPaths);
    }

    private function getFromArray(string $key, array $array, mixed $default): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    private function setInArray(string $key, mixed $value, array &$array): void
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $k = array_shift($keys);

            if (!isset($array[$k]) || !is_array($array[$k])) {
                $array[$k] = [];
            }

            $array = &$array[$k];
        }

        $array[array_shift($keys)] = $value;
    }
}
