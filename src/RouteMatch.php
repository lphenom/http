<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Result of a successful route match.
 *
 * Replaces the anonymous array returned by Router::match() to provide
 * strict typing compatible with KPHP (no mixed-type arrays).
 */
final class RouteMatch
{
    /**
     * @param array<string, string> $params
     */
    public function __construct(
        public readonly HandlerInterface $handler,
        public readonly array $params,
    ) {
    }
}
