<?php

declare(strict_types=1);

namespace LPhenom\Http\Middleware;

use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * CORS Middleware.
 *
 * Handles preflight OPTIONS requests and injects Access-Control headers
 * into all responses.
 *
 * KPHP-compatible: no constructor property promotion, no readonly.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    /** @var string[] */
    private array $allowedMethods;

    /** @var string[] */
    private array $allowedHeaders;

    /** @var bool */
    private bool $allowCredentials;

    /** @var int */
    private int $maxAge;

    /**
     * @param string[] $allowedOrigins  e.g. ['https://example.com'] or ['*']
     * @param string[] $allowedMethods  e.g. ['GET', 'POST', 'OPTIONS']
     * @param string[] $allowedHeaders  e.g. ['Content-Type', 'Authorization']
     */
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        bool $allowCredentials = false,
        int $maxAge = 86400,
    ) {
        $this->allowedOrigins   = $allowedOrigins;
        $this->allowedMethods   = $allowedMethods;
        $this->allowedHeaders   = $allowedHeaders;
        $this->allowCredentials = $allowCredentials;
        $this->maxAge           = $maxAge;
    }

    public function process(Request $request, Next $next): Response
    {
        $origin = $request->getHeader('Origin') ?? '';
        $allowedOrigin = $this->resolveOrigin($origin);

        // Preflight
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->buildCorsResponse($allowedOrigin, new Response(204));
        }

        $response = $next->handle($request);
        return $this->buildCorsResponse($allowedOrigin, $response);
    }

    private function resolveOrigin(string $origin): string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return $this->allowCredentials ? $origin : '*';
        }
        if ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }
        return '';
    }

    private function buildCorsResponse(string $allowedOrigin, Response $response): Response
    {
        if ($allowedOrigin === '') {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($allowedOrigin !== '*') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        return $response;
    }
}
