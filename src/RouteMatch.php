<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Result of a successful route match.
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
 */
final class RouteMatch
{
    /** @var HandlerInterface */
    public HandlerInterface $handler;

    /** @var array<string, string> */
    public array $params;

    /**
     * @param array<string, string> $params
     */
    public function __construct(HandlerInterface $handler, array $params)
    {
        $this->handler = $handler;
        $this->params  = $params;
    }
}
