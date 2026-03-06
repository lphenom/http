<?php

declare(strict_types=1);

namespace LPhenom\Http\Middleware;

use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * CSRF Protection Middleware — stub implementation.
 *
 * For mutating methods (POST/PUT/PATCH/DELETE) it validates the
 * X-CSRF-Token request header against an HMAC-derived token.
 *
 * TODO: implement full CSRF token storage (session / cookie double-submit).
 *
 * Current behaviour:
 * - GET/HEAD/OPTIONS requests pass through without check.
 * - Mutating requests must carry a valid X-CSRF-Token header.
 *   The expected token is hmac_sha256($sessionId, $secret).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly string $secret,
        private readonly string $sessionId = '',
    ) {
    }

    public function process(Request $request, Next $next): Response
    {
        if (in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $expected = $this->generateToken($this->sessionId);
        $provided = $request->getHeader('X-CSRF-Token') ?? '';

        if (!hash_equals($expected, $provided)) {
            return Response::text('Forbidden', 403);
        }

        return $next($request);
    }

    /**
     * Generate a CSRF token for a given session identifier.
     * Expose this so your view layer can embed the token.
     */
    public function generateToken(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, $this->secret);
    }
}

