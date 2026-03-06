<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Composes middleware into a pipeline and runs it against a handler.
 *
 * Usage:
 *   $stack = new MiddlewareStack();
 *   $stack->add(new CorsMiddleware(...));
 *   $stack->add(new RateLimitMiddleware(...));
 *   $response = $stack->run($request, $handler);
 */
final class MiddlewareStack
{
    /** @var MiddlewareInterface[] */
    private array $middleware = [];

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function run(Request $request, HandlerInterface $handler): Response
    {
        $next = new Next($this->middleware, $handler);
        return $next($request);
    }
}

