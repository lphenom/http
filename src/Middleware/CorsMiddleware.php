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
 */
final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $allowedOrigins  e.g. ['https://example.com'] or ['*']
     * @param string[] $allowedMethods  e.g. ['GET', 'POST', 'OPTIONS']
     * @param string[] $allowedHeaders  e.g. ['Content-Type', 'Authorization']
     */
    public function __construct(
        private readonly array $allowedOrigins = ['*'],
        private readonly array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private readonly array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'],
        private readonly bool $allowCredentials = false,
        private readonly int $maxAge = 86400,
    ) {
    }

    public function process(Request $request, Next $next): Response
    {
        $origin = $request->getHeader('Origin') ?? '';
        $allowedOrigin = $this->resolveOrigin($origin);

        // Preflight
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->buildCorsResponse($allowedOrigin, new Response(204));
        }

        $response = $next($request);
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

