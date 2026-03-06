<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Represents "the rest of the pipeline" passed into each middleware.
 *
 * Implemented as an iterable class with an index pointer — no closures,
 * no recursive calls, fully KPHP-compatible.
 */
final class Next
{
    private int $index = 0;

    /**
     * @param MiddlewareInterface[] $middleware
     */
    public function __construct(
        private readonly array $middleware,
        private readonly HandlerInterface $handler,
    ) {
    }

    /**
     * Advance the pipeline by one step.
     */
    public function __invoke(Request $request): Response
    {
        if ($this->index < count($this->middleware)) {
            $current = $this->middleware[$this->index];
            $this->index++;
            return $current->process($request, $this);
        }

        return $this->handler->handle($request);
    }
}
