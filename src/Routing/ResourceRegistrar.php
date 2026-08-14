<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing;

use TondbadSwoole\Core\Route\Route;

class ResourceRegistrar
{
    /**
     * @var array<string, array{method: string|list<string>, uri: string, action: string}>
     */
    private array $resourceActions = [
        'index'   => ['method' => 'GET',    'uri' => '',               'action' => 'index'],
        'create'  => ['method' => 'GET',    'uri' => '/create',        'action' => 'create'],
        'store'   => ['method' => 'POST',   'uri' => '',               'action' => 'store'],
        'show'    => ['method' => 'GET',    'uri' => '/{__param__}',   'action' => 'show'],
        'edit'    => ['method' => 'GET',    'uri' => '/{__param__}/edit', 'action' => 'edit'],
        'update'  => ['method' => ['PUT', 'PATCH'], 'uri' => '/{__param__}', 'action' => 'update'],
        'destroy' => ['method' => 'DELETE', 'uri' => '/{__param__}',   'action' => 'destroy'],
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function register(Route $route, string $name, string $controller, array $options = []): void
    {
        $api = (bool) ($options['api'] ?? false);
        $only = isset($options['only']) ? (array) $options['only'] : null;
        $except = isset($options['except']) ? (array) $options['except'] : [];
        $parameters = isset($options['parameters']) && is_array($options['parameters']) ? $options['parameters'] : [];

        $segments = explode('.', $name);
        $basePath = $this->buildBasePath($segments, $parameters);
        $resourceName = $segments[count($segments) - 1];
        $parameter = $parameters[$resourceName] ?? $this->pluralToSingular($resourceName);

        foreach ($this->resourceActions as $action => $config) {
            if ($api && in_array($action, ['create', 'edit'], true)) {
                continue;
            }

            if ($only !== null && !in_array($action, $only, true)) {
                continue;
            }

            if (in_array($action, $except, true)) {
                continue;
            }

            $uri = $basePath . str_replace('__param__', $parameter, $config['uri']);

            $route->addRoute(
                $config['method'],
                $uri,
                [$controller, $config['action']],
                [],
                $name . '.' . $action
            );
        }
    }

    /**
     * @param list<string> $segments
     * @param array<string, string> $parameters
     */
    private function buildBasePath(array $segments, array $parameters = []): string
    {
        $parts = [];

        foreach ($segments as $index => $segment) {
            $parts[] = $segment;

            if ($index < count($segments) - 1) {
                $parts[] = '{' . ($parameters[$segment] ?? $this->pluralToSingular($segment)) . '}';
            }
        }

        return '/' . implode('/', $parts);
    }

    private function pluralToSingular(string $word): string
    {
        $lower = strtolower($word);

        $irregular = [
            'children' => 'child',
            'people' => 'person',
            'men' => 'man',
            'women' => 'woman',
            'teeth' => 'tooth',
            'feet' => 'foot',
            'mice' => 'mouse',
            'geese' => 'goose',
            'oxen' => 'ox',
        ];

        if (isset($irregular[$lower])) {
            return $this->matchCase($word, $irregular[$lower]);
        }

        if (str_ends_with($lower, 'ies')) {
            $before = substr($lower, -4, 1);

            if ($before !== '' && !in_array($before, ['a', 'e', 'i', 'o', 'u'], true)) {
                return substr($word, 0, -3) . $this->matchCase($word, 'y');
            }
        }

        if (str_ends_with($lower, 'les')) {
            return substr($word, 0, -1);
        }

        if (str_ends_with($lower, 'sses')) {
            return substr($word, 0, -2);
        }

        if (preg_match('/([sxz]|[cs]h|[^aeiou]o)es$/', $lower, $matches)) {
            return preg_replace('/([sxz]|[cs]h|[^aeiou]o)es$/', $matches[1], $word);
        }

        if (str_ends_with($lower, 's') && !str_ends_with($lower, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    private function matchCase(string $original, string $replacement): string
    {
        if ($original === strtolower($original)) {
            return strtolower($replacement);
        }

        if ($original === strtoupper($original)) {
            return strtoupper($replacement);
        }

        if ($original[0] === strtoupper($original[0])) {
            return ucfirst(strtolower($replacement));
        }

        return $replacement;
    }
}
