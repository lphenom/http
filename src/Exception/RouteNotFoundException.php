<?php

declare(strict_types=1);

namespace LPhenom\Http\Exception;

/**
 * Thrown when no route matches the incoming request.
 */
final class RouteNotFoundException extends \RuntimeException
{
    public function __construct(string $method, string $path)
    {
        parent::__construct(
            sprintf('No route found for %s %s', strtoupper($method), $path)
        );
    }
}

