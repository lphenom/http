<?php

declare(strict_types=1);

namespace LPhenom\Http\Middleware;

/**
 * Rate limiter contract used by RateLimitMiddleware.
 *
 * Intentionally kept inside lphenom/http to avoid hard dependencies on
 * lphenom/cache. Implementations backed by cache/Redis/memory can be
 * injected from outside.
 */
interface RateLimiterInterface
{
    /**
     * Check whether the given client is allowed to proceed.
     *
     * Implementations should track hit counts and return false
     * when the limit is exceeded.
     */
    public function isAllowed(string $clientIp): bool;
}

