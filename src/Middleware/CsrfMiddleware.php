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
 * KPHP-compatible: no constructor property promotion, no readonly, no hash_equals.
 * hash_equals() is not available in KPHP — use hash comparison via hash() to
 * avoid timing oracle (both values are hashed before comparison).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private static array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    /** @var string */
    private string $secret;

    /** @var string */
    private string $sessionId;

    public function __construct(string $secret, string $sessionId = '')
    {
        $this->secret    = $secret;
        $this->sessionId = $sessionId;
    }

    public function process(Request $request, Next $next): Response
    {
        if (in_array(strtoupper($request->getMethod()), self::$safeMethods, true)) {
            return $next->handle($request);
        }

        $expected = $this->generateToken($this->sessionId);
        $provided = $request->getHeader('X-CSRF-Token') ?? '';

        // hash_equals is supported in KPHP (see builtin-functions/kphp-light/hash.txt)
        if (!hash_equals($expected, $provided)) {
            return Response::text('Forbidden', 403);
        }

        return $next->handle($request);
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
