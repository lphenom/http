<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * HTTP Router with prefix-index optimisation.
 *
 * Registers routes, supports named routes and group prefixes.
 * Patterns are compiled once at registration time — no eval, no reflection.
 *
 * KPHP-compatible: no closures, no mixed-typed arrays holding objects,
 * no constructor property promotion, no readonly, no array destructuring.
 * Uses parallel arrays to keep strict typing for KPHP.
 */
final class Router
{
    /**
     * Route methods (e.g. 'GET', 'POST').
     *
     * @var string[]
     */
    private array $routeMethods = [];

    /**
     * Route path patterns (e.g. '/users/{id}').
     *
     * @var string[]
     */
    private array $routePatterns = [];

    /**
     * Compiled regexes for dynamic routes (empty string for static routes).
     *
     * @var string[]
     */
    private array $routeRegexes = [];

    /**
     * Parameter names per route.
     *
     * @var array<int, string[]>
     */
    private array $routeParams = [];

    /**
     * Handlers indexed by route index.
     *
     * @var HandlerInterface[]
     */
    private array $routeHandlers = [];

    /**
     * Named route indices: name → route index.
     *
     * @var array<string, int>
     */
    private array $namedRoutes = [];

    /**
     * Prefix index: first path segment → list of route indices.
     *
     * @var array<string, int[]>
     */
    private array $index = [];

    /** @var string */
    private string $currentPrefix = '';

    public function add(string $method, string $path, HandlerInterface $handler): self
    {
        $fullPath = $this->currentPrefix . '/' . ltrim($path, '/');

        // Normalize double slashes
        while (strpos($fullPath, '//') !== false) {
            $fullPath = str_replace('//', '/', $fullPath);
        }
        if ($fullPath !== '/' && substr($fullPath, -1) === '/') {
            $fullPath = rtrim($fullPath, '/');
        }

        /** @var string[] $params */
        $params = [];
        $regex  = $this->buildRegex($fullPath, $params);

        $idx = count($this->routeMethods);

        $this->routeMethods[$idx]  = strtoupper($method);
        $this->routePatterns[$idx] = $fullPath;
        $this->routeRegexes[$idx]  = $regex;
        $this->routeParams[$idx]   = $params;
        $this->routeHandlers[$idx] = $handler;

        // Build prefix index from the first segment
        $firstSegment = $this->firstSegment($fullPath);
        if (!isset($this->index[$firstSegment])) {
            $this->index[$firstSegment] = [];
        }
        $this->index[$firstSegment][] = $idx;

        return $this;
    }

    public function get(string $path, HandlerInterface $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, HandlerInterface $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, HandlerInterface $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, HandlerInterface $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, HandlerInterface $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Group routes under a common prefix.
     *
     * KPHP note: \Closure is not reliably storable in arrays under KPHP.
     * The callback is called immediately — it is never stored.
     */
    public function group(string $prefix, RouterGroupCallback $callback): self
    {
        $previousPrefix      = $this->currentPrefix;
        $this->currentPrefix = $previousPrefix . '/' . ltrim($prefix, '/');

        $callback->call($this);

        $this->currentPrefix = $previousPrefix;

        return $this;
    }

    /**
     * Assign a name to the last registered route.
     */
    public function name(string $routeName): self
    {
        $last = count($this->routeMethods) - 1;
        if ($last >= 0) {
            $this->namedRoutes[$routeName] = $last;
        }
        return $this;
    }

    /**
     * Match method + path against registered routes.
     *
     * Returns RouteMatch with handler and extracted params, or null if no match.
     * Uses prefix index to skip irrelevant routes.
     */
    public function match(string $method, string $path): ?RouteMatch
    {
        $method = strtoupper($method);
        $path   = $path === '' ? '/' : $path;

        $firstSegment = $this->firstSegment($path);

        /** @var int[] $candidates */
        $candidates = isset($this->index[$firstSegment]) ? $this->index[$firstSegment] : [];

        // Also check wildcard/root segment for routes starting with a param
        if ($firstSegment !== '*') {
            /** @var int[] $wildcardCandidates */
            $wildcardCandidates = isset($this->index['*']) ? $this->index['*'] : [];
            foreach ($wildcardCandidates as $wc) {
                $candidates[] = $wc;
            }
        }

        foreach ($candidates as $idx) {
            if ($this->routeMethods[$idx] !== $method) {
                continue;
            }

            $routeParams = $this->routeParams[$idx];

            if ($routeParams === []) {
                // Static route — direct comparison
                if ($this->routePatterns[$idx] === $path) {
                    return new RouteMatch($this->routeHandlers[$idx], []);
                }
                continue;
            }

            // Dynamic route — regex match
            /** @var string[] $matches */
            $matches = [];
            if (preg_match($this->routeRegexes[$idx], $path, $matches) === 1) {
                /** @var array<string, string> $params */
                $params = [];
                foreach ($routeParams as $name) {
                    if (isset($matches[$name])) {
                        $params[$name] = (string) $matches[$name];
                    }
                }
                return new RouteMatch($this->routeHandlers[$idx], $params);
            }
        }

        return null;
    }

    /**
     * Return named route pattern or null.
     */
    public function getNamedRoute(string $routeName): ?string
    {
        if (!isset($this->namedRoutes[$routeName])) {
            return null;
        }
        $idx = $this->namedRoutes[$routeName];
        return $this->routePatterns[$idx];
    }

    /**
     * Build a regex from a route pattern and collect param names.
     *
     * /users/{id}/posts/{slug} → regex + ['id', 'slug']
     *
     * Uses manual parsing instead of preg_split + PREG_SPLIT_DELIM_CAPTURE
     * for KPHP compatibility (PREG_SPLIT_DELIM_CAPTURE support is unreliable).
     *
     * @param string[] $params  filled by reference
     * @return string  full regex with anchors
     */
    private function buildRegex(string $pattern, array &$params): string
    {
        $regex  = '#^';
        $len    = strlen($pattern);
        $i      = 0;
        $static = '';

        while ($i < $len) {
            if ($pattern[$i] === '{') {
                // Flush buffered static segment
                if ($static !== '') {
                    $regex .= preg_quote($static, '#');
                    $static = '';
                }
                // Read param name until '}'
                $j = $i + 1;
                while ($j < $len && $pattern[$j] !== '}') {
                    $j++;
                }
                $name     = substr($pattern, $i + 1, $j - $i - 1);
                $params[] = $name;
                $regex   .= '(?P<' . $name . '>[^/]+)';
                $i        = $j + 1;
            } else {
                $static .= $pattern[$i];
                $i++;
            }
        }

        if ($static !== '') {
            $regex .= preg_quote($static, '#');
        }

        $regex .= '$#';

        return $regex;
    }

    /**
     * Extract the first meaningful path segment for prefix indexing.
     * /api/users/42  → "api"
     * /              → "/"
     * /users         → "users"
     * /{id}/...      → "*"
     */
    private function firstSegment(string $path): string
    {
        $trimmed = ltrim($path, '/');
        if ($trimmed === '') {
            return '/';
        }
        $slashPos = strpos($trimmed, '/');
        $segment  = $slashPos === false ? $trimmed : substr($trimmed, 0, $slashPos);

        // Dynamic placeholder → wildcard bucket
        if (substr($segment, 0, 1) === '{') {
            return '*';
        }

        return $segment;
    }
}
