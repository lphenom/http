<?php

declare(strict_types=1);

namespace LPhenom\Http\Middleware;

use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * Rate Limit Middleware.
 *
 * Delegates the actual counting/storage to a RateLimiterInterface
 * implementation, keeping this class decoupled from lphenom/cache.
 *
 * Usage:
 *   $middleware = new RateLimitMiddleware(new MyRedisRateLimiter());
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterInterface $limiter,
    ) {
    }

    public function process(Request $request, Next $next): Response
    {
        if (!$this->limiter->isAllowed($request->getClientIp())) {
            return Response::text('Too Many Requests', 429);
        }

        return $next($request);
    }
}

