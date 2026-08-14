<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use TondbadSwoole\Core\Route\Route;

class FileRouteLoader
{
    /**
     * @param array<int, class-string> $middlewares
     */
    public function load(string $directory, Route $route, array $middlewares = []): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $dirMiddlewares = $this->loadMiddleware($directory);
        $middlewares = array_merge($middlewares, $dirMiddlewares);

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                if ($entry[0] === '(' && str_ends_with($entry, ')')) {
                    $this->load($path, $route, $middlewares);
                } else {
                    $this->loadDirectory($path, $route, $this->buildSegment($entry), $middlewares);
                }

                continue;
            }

            if (!str_ends_with($entry, '.php') || $entry === '_middleware.php') {
                continue;
            }

            $this->loadFile($path, $route, $this->buildSegment(basename($entry, '.php')), $middlewares);
        }
    }

    /**
     * @param array<int, class-string> $middlewares
     */
    private function loadDirectory(string $directory, Route $route, string $segment, array $middlewares = []): void
    {
        $dirMiddlewares = $this->loadMiddleware($directory);
        $middlewares = array_merge($middlewares, $dirMiddlewares);

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                if ($entry[0] === '(' && str_ends_with($entry, ')')) {
                    $this->load($path, $route, $middlewares);
                } else {
                    $this->loadDirectory($path, $route, $this->appendSegment($segment, $this->buildSegment($entry)), $middlewares);
                }

                continue;
            }

            if (!str_ends_with($entry, '.php') || $entry === '_middleware.php') {
                continue;
            }

            $name = basename($entry, '.php');

            if ($name === 'index') {
                $this->loadFile($path, $route, $segment, $middlewares);
            } else {
                $this->loadFile($path, $route, $this->appendSegment($segment, $this->buildSegment($name)), $middlewares);
            }
        }
    }

    /**
     * @param array<int, class-string> $middlewares
     */
    private function loadFile(string $file, Route $route, string $uri, array $middlewares = []): void
    {
        $exported = require $file;

        if ($uri === '') {
            $uri = '/';
        }

        if (is_callable($exported)) {
            $exported = ['GET' => $exported];
        }

        if (!is_array($exported)) {
            throw new InvalidArgumentException("Route file [{$file}] must return a callable or an array of method => handler.");
        }

        foreach ($exported as $method => $handler) {
            if (!is_string($method)) {
                throw new InvalidArgumentException("Route file [{$file}] method keys must be HTTP method strings.");
            }

            if (!is_callable($handler)) {
                throw new InvalidArgumentException("Route file [{$file}] handler for [{$method}] must be callable.");
            }

            $route->addRoute($method, $uri, $handler, $middlewares);
        }
    }

    /**
     * @param array<int, class-string> $middlewares
     * @return array<int, class-string>
     */
    private function loadMiddleware(string $directory): array
    {
        $file = $directory . '/_middleware.php';

        if (!is_file($file)) {
            return [];
        }

        $exported = require $file;

        if (!is_array($exported)) {
            throw new InvalidArgumentException("Middleware file [{$file}] must return an array of middleware class names.");
        }

        return array_values($exported);
    }

    private function buildSegment(string $name): string
    {
        if ($name === 'index') {
            return '';
        }

        if (preg_match('/^\[\.\.\.(?P<param>[a-zA-Z_]\w*)\]$/', $name, $matches)) {
            return '/{' . $matches['param'] . ':.*}';
        }

        if (preg_match('/^\[\[\.\.\.(?P<param>[a-zA-Z_]\w*)\]\]$/', $name, $matches)) {
            return '[/{' . $matches['param'] . ':.*}]';
        }

        if (preg_match('/^\[(?P<param>[a-zA-Z_]\w*)\]$/', $name, $matches)) {
            return '/{' . $matches['param'] . '}';
        }

        return '/' . $name;
    }

    private function appendSegment(string $base, string $segment): string
    {
        if ($segment === '') {
            return $base;
        }

        if (str_starts_with($segment, '[') && $base !== '') {
            return $base . $segment;
        }

        if ($base === '' && $segment === '') {
            return '';
        }

        return $base . $segment;
    }
}
