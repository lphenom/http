<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Represents "the rest of the pipeline" passed into each middleware.
 *
 * Implemented as an iterable class with an index pointer — no closures,
 * no recursive calls, fully KPHP-compatible.
 *
 * KPHP note: __invoke() is NOT supported in KPHP.
 * Use handle() method explicitly instead.
 * No constructor property promotion, no readonly.
 */
final class Next
{
    /** @var array<int, MiddlewareInterface> */
    private array $middleware;

    /** @var HandlerInterface */
    private HandlerInterface $handler;

    /** @var int */
    private int $index = 0;

    /**
     * @param array<int, MiddlewareInterface> $middleware
     */
    public function __construct(array $middleware, HandlerInterface $handler)
    {
        $this->middleware = $middleware;
        $this->handler    = $handler;
    }

    /**
     * Advance the pipeline by one step.
     *
     * KPHP-compatible replacement for __invoke().
     * Called by middleware as $next->handle($request).
     */
    public function handle(Request $request): Response
    {
        if ($this->index < count($this->middleware)) {
            $current = $this->middleware[$this->index];
            $this->index++;
            return $current->process($request, $this);
        }

        return $this->handler->handle($request);
    }
}
