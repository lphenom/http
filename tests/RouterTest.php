<?php

declare(strict_types=1);

namespace LPhenom\Http\Tests;

use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\RouteMatch;
use LPhenom\Http\Router;
use LPhenom\Http\RouterGroupCallback;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeHandler(): HandlerInterface
    {
        return new class () implements HandlerInterface {
            public function handle(Request $request): Response
            {
                return Response::text('ok');
            }
        };
    }

    // -------------------------------------------------------------------------
    // Static route
    // -------------------------------------------------------------------------

    public function testStaticRouteMatch(): void
    {
        $router = new Router();
        $handler = $this->makeHandler();
        $router->add('GET', '/users', $handler);

        $result = $router->match('GET', '/users');

        self::assertNotNull($result);
        self::assertInstanceOf(RouteMatch::class, $result);
        self::assertSame($handler, $result->handler);
        self::assertSame([], $result->params);
    }

    // -------------------------------------------------------------------------
    // Dynamic route / params
    // -------------------------------------------------------------------------

    public function testDynamicRouteMatchExtractsParam(): void
    {
        $router = new Router();
        $router->add('GET', '/users/{id}', $this->makeHandler());

        $result = $router->match('GET', '/users/42');

        self::assertNotNull($result);
        self::assertSame(['id' => '42'], $result->params);
    }

    public function testDynamicRouteWithMultipleParams(): void
    {
        $router = new Router();
        $router->add('GET', '/posts/{year}/{slug}', $this->makeHandler());

        $result = $router->match('GET', '/posts/2024/hello-world');

        self::assertNotNull($result);
        self::assertSame(['year' => '2024', 'slug' => 'hello-world'], $result->params);
    }

    // -------------------------------------------------------------------------
    // Not found / method mismatch
    // -------------------------------------------------------------------------

    public function testRouteNotFound(): void
    {
        $router = new Router();
        $router->add('GET', '/users', $this->makeHandler());

        $result = $router->match('GET', '/nonexistent');

        self::assertNull($result);
    }

    public function testMethodMismatch(): void
    {
        $router = new Router();
        $router->add('GET', '/users', $this->makeHandler());

        $result = $router->match('POST', '/users');

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Route groups
    // -------------------------------------------------------------------------

    public function testGroupPrefix(): void
    {
        $router = new Router();
        $handler = $this->makeHandler();

        $router->group('/api', new class ($handler) implements RouterGroupCallback {
            public function __construct(private readonly HandlerInterface $handler)
            {
            }

            public function call(Router $r): void
            {
                $r->add('GET', '/users', $this->handler);
            }
        });

        $result = $router->match('GET', '/api/users');

        self::assertNotNull($result);
        self::assertSame($handler, $result->handler);
    }

    public function testNestedGroupPrefix(): void
    {
        $router = new Router();
        $handler = $this->makeHandler();

        $router->group('/api', new class ($handler) implements RouterGroupCallback {
            public function __construct(private readonly HandlerInterface $handler)
            {
            }

            public function call(Router $r): void
            {
                $r->group('/v1', new class ($this->handler) implements RouterGroupCallback {
                    public function __construct(private readonly HandlerInterface $handler)
                    {
                    }

                    public function call(Router $r): void
                    {
                        $r->add('GET', '/users', $this->handler);
                    }
                });
            }
        });

        self::assertNotNull($router->match('GET', '/api/v1/users'));
    }

    public function testGroupDoesNotLeakPrefix(): void
    {
        $router = new Router();
        $handler = $this->makeHandler();

        $router->group('/api', new class ($handler) implements RouterGroupCallback {
            public function __construct(private readonly HandlerInterface $handler)
            {
            }

            public function call(Router $r): void
            {
                $r->add('GET', '/users', $this->handler);
            }
        });
        $router->add('GET', '/health', $handler);

        // Route added after group should NOT have the group prefix
        self::assertNull($router->match('GET', '/api/health'));
        self::assertNotNull($router->match('GET', '/health'));
    }

    // -------------------------------------------------------------------------
    // Named routes
    // -------------------------------------------------------------------------

    public function testRouteName(): void
    {
        $router = new Router();
        $router->add('GET', '/users/{id}', $this->makeHandler())->name('user.show');

        $pattern = $router->getNamedRoute('user.show');

        self::assertSame('/users/{id}', $pattern);
    }

    public function testUnknownNamedRouteReturnsNull(): void
    {
        $router = new Router();

        self::assertNull($router->getNamedRoute('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // HTTP method helpers
    // -------------------------------------------------------------------------

    public function testGetHelper(): void
    {
        $router = new Router();
        $router->get('/ping', $this->makeHandler());

        self::assertNotNull($router->match('GET', '/ping'));
    }

    public function testPostHelper(): void
    {
        $router = new Router();
        $router->post('/items', $this->makeHandler());

        self::assertNotNull($router->match('POST', '/items'));
        self::assertNull($router->match('GET', '/items'));
    }

    public function testDeleteHelper(): void
    {
        $router = new Router();
        $router->delete('/items/{id}', $this->makeHandler());

        self::assertNotNull($router->match('DELETE', '/items/5'));
        self::assertNull($router->match('GET', '/items/5'));
    }

    // -------------------------------------------------------------------------
    // Method case normalisation
    // -------------------------------------------------------------------------

    public function testMethodIsCaseInsensitive(): void
    {
        $router = new Router();
        $router->add('GET', '/ping', $this->makeHandler());

        self::assertNotNull($router->match('get', '/ping'));
        self::assertNotNull($router->match('Get', '/ping'));
    }

    // -------------------------------------------------------------------------
    // Root route
    // -------------------------------------------------------------------------

    public function testRootRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/', $this->makeHandler());

        self::assertNotNull($router->match('GET', '/'));
    }
}
