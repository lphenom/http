<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Middleware contract.
 *
 * Implementations must call $next->handle($request) to pass control downstream
 * or return a Response directly to short-circuit the pipeline.
 *
 * KPHP-compatible: explicit typed interface, no magic.
 * Note: use $next->handle($request) — NOT $next($request), as __invoke()
 * is not supported in KPHP.
 */
interface MiddlewareInterface
{
    public function process(Request $request, Next $next): Response;
}
