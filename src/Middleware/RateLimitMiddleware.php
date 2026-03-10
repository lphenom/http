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
 * KPHP-compatible: no constructor property promotion, no readonly.
 *
 * Usage:
 *   $middleware = new RateLimitMiddleware(new MyRedisRateLimiter());
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var RateLimiterInterface */
    private RateLimiterInterface $limiter;

    public function __construct(RateLimiterInterface $limiter)
    {
        $this->limiter = $limiter;
    }

    public function process(Request $request, Next $next): Response
    {
        if (!$this->limiter->isAllowed($request->getClientIp())) {
            return Response::text('Too Many Requests', 429);
        }

        return $next->handle($request);
    }
}
