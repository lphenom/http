<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * HTTP Router with prefix-index optimisation.
 *
 * Registers routes, supports named routes and group prefixes.
 * Patterns are compiled once at registration time — no eval, no reflection.
 *
 * KPHP-compatible: no closures captured by reference in hot paths.
 */
final class Router
{
    /**
     * Each entry:
     *  ['method' => string, 'pattern' => string, 'regex' => string,
     *   'params' => string[], 'handler' => HandlerInterface, 'name' => ?string]
     *
     * @var array<int, array{method: string, pattern: string, regex: string, params: string[], handler: HandlerInterface, name: string|null}>
     */
    private array $routes = [];

    /**
     * Prefix index: first path segment → list of route indices.
     *
     * @var array<string, int[]>
     */
    private array $index = [];

    /** Current group prefix applied to new routes. */
    private string $currentPrefix = '';

    public function add(string $method, string $path, HandlerInterface $handler): self
    {
        $fullPath = $this->currentPrefix . '/' . ltrim($path, '/');

        // Normalize double slashes
        while (str_contains($fullPath, '//')) {
            $fullPath = str_replace('//', '/', $fullPath);
        }
        if ($fullPath !== '/' && str_ends_with($fullPath, '/')) {
            $fullPath = rtrim($fullPath, '/');
        }

        [$regex, $params] = $this->compilePattern($fullPath);

        $index = count($this->routes);
        $this->routes[$index] = [
            'method'  => strtoupper($method),
            'pattern' => $fullPath,
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
            'name'    => null,
        ];

        // Build prefix index from the first segment
        $firstSegment = $this->firstSegment($fullPath);
        if (!isset($this->index[$firstSegment])) {
            $this->index[$firstSegment] = [];
        }
        $this->index[$firstSegment][] = $index;

        return $this;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, HandlerInterface $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, HandlerInterface $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, HandlerInterface $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, HandlerInterface $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, HandlerInterface $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Group routes under a common prefix.
     * Callback receives this Router instance.
     *
     * @param callable(self): void $callback
     */
    public function group(string $prefix, callable $callback): self
    {
        $previousPrefix = $this->currentPrefix;
        $this->currentPrefix = $previousPrefix . '/' . ltrim($prefix, '/');

        $callback($this);

        $this->currentPrefix = $previousPrefix;

        return $this;
    }

    /**
     * Assign a name to the last registered route.
     */
    public function name(string $routeName): self
    {
        $last = count($this->routes) - 1;
        if ($last >= 0) {
            $this->routes[$last]['name'] = $routeName;
        }
        return $this;
    }

    /**
     * Match method + path against registered routes.
     *
     * Returns ['handler' => HandlerInterface, 'params' => array<string,string>] or null.
     *
     * Uses prefix index to skip irrelevant routes.
     *
     * @return array{handler: HandlerInterface, params: array<string, string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = $path === '' ? '/' : $path;

        // Collect candidate indices via prefix index
        $firstSegment = $this->firstSegment($path);
        $candidates = $this->index[$firstSegment] ?? [];

        // Also check wildcard/root segment
        if ($firstSegment !== '*') {
            $wildcardCandidates = $this->index['*'] ?? [];
            $candidates = array_merge($candidates, $wildcardCandidates);
        }

        foreach ($candidates as $idx) {
            $route = $this->routes[$idx];

            if ($route['method'] !== $method) {
                continue;
            }

            if ($route['params'] === []) {
                // Static route — direct comparison
                if ($route['pattern'] === $path) {
                    return ['handler' => $route['handler'], 'params' => []];
                }
                continue;
            }

            // Dynamic route — regex match
            $matches = [];
            if (preg_match($route['regex'], $path, $matches) === 1) {
                /** @var array<string, string> $params */
                $params = [];
                foreach ($route['params'] as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = (string) $matches[$name];
                    }
                }
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }

        return null;
    }

    /**
     * Return named route pattern or null.
     */
    public function getNamedRoute(string $routeName): ?string
    {
        foreach ($this->routes as $route) {
            if ($route['name'] === $routeName) {
                return $route['pattern'];
            }
        }
        return null;
    }

    /**
     * Compile a route pattern into a regex and extract parameter names.
     *
     * /users/{id}/posts/{slug} → regex + ['id', 'slug']
     *
     * @return array{string, string[]}
     */
    private function compilePattern(string $pattern): array
    {
        $params = [];
        $regex = $this->buildRegex($pattern, $params);

        return [$regex, $params];
    }

    /**
     * @param string[] $params  filled by reference
     * @return string  full regex with anchors
     */
    private function buildRegex(string $pattern, array &$params): string
    {
        $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return '#^' . preg_quote($pattern, '#') . '$#';
        }

        $regex = '#^';
        foreach ($parts as $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $name = substr($part, 1, -1);
                $params[] = $name;
                $regex .= '(?P<' . $name . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        $regex .= '$#';

        return $regex;
    }

    /**
     * Extract the first meaningful path segment for indexing.
     * /api/users/42  → "api"
     * /              → "/"
     * /users         → "users"
     */
    private function firstSegment(string $path): string
    {
        $trimmed = ltrim($path, '/');
        if ($trimmed === '') {
            return '/';
        }
        $slashPos = strpos($trimmed, '/');
        $segment = $slashPos === false ? $trimmed : substr($trimmed, 0, $slashPos);

        // If the segment is a dynamic placeholder, use wildcard key
        if (str_starts_with($segment, '{')) {
            return '*';
        }

        return $segment;
    }
}
