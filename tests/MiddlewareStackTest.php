<?php

declare(strict_types=1);

namespace LPhenom\Http\Tests;

use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Middleware\CorsMiddleware;
use LPhenom\Http\Middleware\CsrfMiddleware;
use LPhenom\Http\Middleware\RateLimiterInterface;
use LPhenom\Http\Middleware\RateLimitMiddleware;
use LPhenom\Http\MiddlewareInterface;
use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Next;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use PHPUnit\Framework\TestCase;

final class MiddlewareStackTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(
        string $method = 'GET',
        string $path = '/',
        string $body = '',
        string $clientIp = '127.0.0.1',
    ): Request {
        return new Request($method, $path, [], [], [], $body, [], $clientIp);
    }

    private function makeRequestWithHeaders(string $method, string $path, array $headers): Request
    {
        return new Request($method, $path, [], $headers, [], '', [], '127.0.0.1');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function makeJsonHandler(array $data = ['ok' => true]): HandlerInterface
    {
        return new class ($data) implements HandlerInterface {
            public function __construct(private readonly array $data)
            {
            }

            public function handle(Request $request): Response
            {
                return Response::json($this->data);
            }
        };
    }

    // -------------------------------------------------------------------------
    // Pipeline order
    // -------------------------------------------------------------------------

    public function testMiddlewareExecutionOrder(): void
    {
        /** @var string[] $log */
        $log = [];

        $middlewareA = new class ($log) implements MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }

            public function process(Request $request, Next $next): Response
            {
                $this->log[] = 'before-A';
                $response = $next->handle($request);
                $this->log[] = 'after-A';
                return $response;
            }
        };

        $middlewareB = new class ($log) implements MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }

            public function process(Request $request, Next $next): Response
            {
                $this->log[] = 'before-B';
                $response = $next->handle($request);
                $this->log[] = 'after-B';
                return $response;
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($middlewareA)->add($middlewareB);

        $stack->run($this->makeRequest(), $this->makeJsonHandler());

        self::assertSame(['before-A', 'before-B', 'after-B', 'after-A'], $log);
    }

    // -------------------------------------------------------------------------
    // Short-circuit
    // -------------------------------------------------------------------------

    public function testMiddlewareCanShortCircuit(): void
    {
        $blockingMiddleware = new class () implements MiddlewareInterface {
            public function process(Request $request, Next $next): Response
            {
                return Response::text('Blocked', 403);
            }
        };

        $calledHandler = false;
        $handler = new class ($calledHandler) implements HandlerInterface {
            public function __construct(private bool &$called)
            {
            }

            public function handle(Request $request): Response
            {
                $this->called = true;
                return Response::text('Should not reach here');
            }
        };

        $stack = new MiddlewareStack();
        $stack->add($blockingMiddleware);

        $response = $stack->run($this->makeRequest(), $handler);

        self::assertSame(403, $response->getStatus());
        self::assertFalse($calledHandler, 'Handler must not be called when middleware short-circuits');
    }

    // -------------------------------------------------------------------------
    // Empty stack
    // -------------------------------------------------------------------------

    public function testEmptyStackCallsHandlerDirectly(): void
    {
        $stack = new MiddlewareStack();
        $response = $stack->run($this->makeRequest(), $this->makeJsonHandler(['result' => 'direct']));

        self::assertSame(200, $response->getStatus());
        self::assertStringContainsString('direct', $response->getBody());
    }

    // -------------------------------------------------------------------------
    // CorsMiddleware
    // -------------------------------------------------------------------------

    public function testCorsMiddlewareHandlesOptionsRequest(): void
    {
        $cors = new CorsMiddleware(['*'], ['GET', 'POST', 'OPTIONS'], ['Content-Type']);

        $request = $this->makeRequestWithHeaders('OPTIONS', '/', ['Origin' => 'https://example.com']);

        $stack = new MiddlewareStack();
        $stack->add($cors);

        $response = $stack->run($request, $this->makeJsonHandler());

        self::assertSame(204, $response->getStatus());
        self::assertSame('*', $response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testCorsMiddlewareAddsHeadersToRegularRequests(): void
    {
        $cors = new CorsMiddleware(['https://app.example.com']);

        $request = $this->makeRequestWithHeaders('GET', '/api', ['Origin' => 'https://app.example.com']);

        $stack = new MiddlewareStack();
        $stack->add($cors);

        $response = $stack->run($request, $this->makeJsonHandler());

        self::assertSame(200, $response->getStatus());
        self::assertSame('https://app.example.com', $response->getHeader('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $response->getHeader('Vary'));
    }

    public function testCorsMiddlewareBlocksDisallowedOrigin(): void
    {
        $cors = new CorsMiddleware(['https://allowed.example.com']);

        $request = $this->makeRequestWithHeaders('GET', '/', ['Origin' => 'https://evil.com']);

        $stack = new MiddlewareStack();
        $stack->add($cors);

        $response = $stack->run($request, $this->makeJsonHandler());

        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
    }

    // -------------------------------------------------------------------------
    // RateLimitMiddleware
    // -------------------------------------------------------------------------

    public function testRateLimitAllowsRequest(): void
    {
        $limiter = new class () implements RateLimiterInterface {
            public function isAllowed(string $clientIp): bool
            {
                return true;
            }
        };

        $stack = new MiddlewareStack();
        $stack->add(new RateLimitMiddleware($limiter));

        $response = $stack->run($this->makeRequest(), $this->makeJsonHandler());

        self::assertSame(200, $response->getStatus());
    }

    public function testRateLimitBlocksRequest(): void
    {
        $limiter = new class () implements RateLimiterInterface {
            public function isAllowed(string $clientIp): bool
            {
                return false;
            }
        };

        $stack = new MiddlewareStack();
        $stack->add(new RateLimitMiddleware($limiter));

        $response = $stack->run($this->makeRequest(), $this->makeJsonHandler());

        self::assertSame(429, $response->getStatus());
        self::assertStringContainsString('Too Many Requests', $response->getBody());
    }

    // -------------------------------------------------------------------------
    // CsrfMiddleware
    // -------------------------------------------------------------------------

    public function testCsrfAllowsSafeMethod(): void
    {
        $csrf = new CsrfMiddleware('secret', 'session-id');

        $stack = new MiddlewareStack();
        $stack->add($csrf);

        $response = $stack->run($this->makeRequest('GET', '/'), $this->makeJsonHandler());

        self::assertSame(200, $response->getStatus());
    }

    public function testCsrfBlocksPostWithoutToken(): void
    {
        $csrf = new CsrfMiddleware('secret', 'session-id');

        $stack = new MiddlewareStack();
        $stack->add($csrf);

        $response = $stack->run($this->makeRequest('POST', '/form'), $this->makeJsonHandler());

        self::assertSame(403, $response->getStatus());
    }

    public function testCsrfAllowsPostWithValidToken(): void
    {
        $secret = 'my-secret';
        $sessionId = 'abc123';
        $csrf = new CsrfMiddleware($secret, $sessionId);
        $token = $csrf->generateToken($sessionId);

        $request = $this->makeRequestWithHeaders('POST', '/form', ['X-CSRF-Token' => $token]);

        $stack = new MiddlewareStack();
        $stack->add($csrf);

        $response = $stack->run($request, $this->makeJsonHandler());

        self::assertSame(200, $response->getStatus());
    }
}
