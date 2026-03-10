# Middleware

`lphenom/http` provides a simple, ordered middleware pipeline with short-circuit support.
All middleware is KPHP-compatible: no closures captured by reference, no reflection.

---

## MiddlewareInterface Contract

Every middleware must implement `MiddlewareInterface`:

```php
use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, Next $next): Response;
}
```

Call `$next->handle($request)` to pass control to the next middleware (or the final handler).
Return a `Response` directly to **short-circuit** the remaining pipeline.

> **KPHP note:** `__invoke()` is NOT supported in KPHP.  
> Always use `$next->handle($request)` — **not** `$next($request)`.

---

## MiddlewareStack Usage

```php
use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Middleware\CorsMiddleware;
use LPhenom\Http\Middleware\RateLimitMiddleware;

$stack = new MiddlewareStack();
$stack->add(new CorsMiddleware(['*']));
$stack->add(new RateLimitMiddleware($myLimiter));

$response = $stack->run($request, $handler);
$response->send();
```

Middleware executes in the order it was added:

```
Request → Middleware A → Middleware B → Handler
Response ←            ←              ←
```

---

## Built-in Middleware

### CorsMiddleware

Handles CORS preflight (`OPTIONS`) and injects `Access-Control-*` headers.

```php
use LPhenom\Http\Middleware\CorsMiddleware;

$cors = new CorsMiddleware(
    allowedOrigins:    ['https://app.example.com'],
    allowedMethods:    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowedHeaders:    ['Content-Type', 'Authorization'],
    allowCredentials:  false,
    maxAge:            86400,
);
```

- `OPTIONS` request → returns `204` with CORS headers, handler is **not** called.
- Other methods → CORS headers are appended to the handler's response.
- `allowedOrigins: ['*']` allows any origin (not sent when `allowCredentials: true`).
- Disallowed origins receive no `Access-Control-*` headers.

---

### CsrfMiddleware *(stub)*

Validates `X-CSRF-Token` header for mutating requests (`POST`, `PUT`, `PATCH`, `DELETE`).

```php
use LPhenom\Http\Middleware\CsrfMiddleware;

$csrf = new CsrfMiddleware(
    secret:    'your-app-secret',
    sessionId: $currentSessionId,
);

// Generate token to embed in forms / SPA:
$token = $csrf->generateToken($currentSessionId);
```

- `GET`, `HEAD`, `OPTIONS` pass through without validation.
- Mutating requests without a valid `X-CSRF-Token` header receive `403 Forbidden`.
- Token is `hmac_sha256($sessionId, $secret)`.

> **TODO:** full CSRF token storage (session-backed / cookie double-submit pattern)
> will be implemented when `lphenom/session` is available.

---

### RateLimitMiddleware

Delegates rate limiting to a `RateLimiterInterface` implementation — keeping
`lphenom/http` decoupled from any specific cache backend.

```php
use LPhenom\Http\Middleware\RateLimitMiddleware;
use LPhenom\Http\Middleware\RateLimiterInterface;

// Implement your own limiter backed by Redis / APCu / lphenom/cache:
final class RedisRateLimiter implements RateLimiterInterface
{
    public function isAllowed(string $clientIp): bool
    {
        // increment counter in Redis, return false when limit exceeded
        return true;
    }
}

$stack->add(new RateLimitMiddleware(new RedisRateLimiter()));
```

Returns `429 Too Many Requests` when `isAllowed()` returns `false`.

---

## Writing Custom Middleware

```php
use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Next $next): Response
    {
        $token = $request->getHeader('Authorization');

        if ($token === null || $token === '') {
            // Short-circuit: return immediately without calling $next
            return Response::text('Unauthorized', 401);
        }

        // Continue pipeline
        return $next->handle($request);
    }
}
```

---

## Next / Short-Circuit

`Next` is a stateful class that advances the pipeline by one step each time it is called.
It is **not** a closure — KPHP-compatible.

> **KPHP note:** use `$next->handle($request)` — NOT `$next($request)`.  
> `__invoke()` is not supported in KPHP.

```
$next->handle($request)   — calls the next middleware or the final handler
return $response          — short-circuits: remaining middleware and handler are skipped
```

Execution model (three middleware, one handler):

```
→ M1.process()
    → $next->handle($request)          // advances to M2
        → M2.process()
            → $next->handle($request)  // advances to M3
                → M3.process()
                    → $next->handle($request)  // calls handler
                    ← Response
                ← Response
            ← Response
        ← Response
    ← Response
← Response
```

If `M2` returns early without calling `$next->handle()`, `M3` and the handler are never called.

---

## Combining Router + MiddlewareStack

```php
$router = new Router();
$router->get('/api/users', new UserListHandler());

$stack = new MiddlewareStack();
$stack->add(new CorsMiddleware(['*']));
$stack->add(new AuthMiddleware());

$request = Request::fromGlobals();
$match   = $router->match($request->getMethod(), $request->getPath());

if ($match === null) {
    Response::text('Not Found', 404)->send();
    exit;
}

$stack->run($request, $match->handler)->send();
```

