# Routing

`lphenom/http` provides a fast prefix-indexed router with support for static and dynamic routes,
named routes, and route groups.

---

## Quick Start

```php
use LPhenom\Http\Router;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\HandlerInterface;

$router = new Router();

$router->get('/health', new class implements HandlerInterface {
    public function handle(Request $request): Response
    {
        return Response::json(['status' => 'ok']);
    }
});

$request = Request::fromGlobals();
$match   = $router->match($request->getMethod(), $request->getPath());

if ($match === null) {
    Response::text('Not Found', 404)->send();
    exit;
}

$match['handler']->handle($request)->send();
```

---

## Registering Routes

### HTTP method helpers

```php
$router->get('/users',        new UserListHandler());
$router->post('/users',       new UserCreateHandler());
$router->put('/users/{id}',   new UserUpdateHandler());
$router->patch('/users/{id}', new UserPatchHandler());
$router->delete('/users/{id}',new UserDeleteHandler());
```

### Generic `add()`

```php
$router->add('GET', '/ping', new PingHandler());
```

Method names are case-insensitive — `GET`, `get`, `Get` are all equivalent.

---

## Route Parameters

Segments wrapped in `{name}` become named parameters extracted during matching.

```php
$router->get('/users/{id}',           new UserShowHandler());
$router->get('/posts/{year}/{slug}',   new PostShowHandler());
```

Parameters are available in `$match['params']`:

```php
$match = $router->match('GET', '/users/42');
// $match['params'] === ['id' => '42']

$match = $router->match('GET', '/posts/2024/hello-world');
// $match['params'] === ['year' => '2024', 'slug' => 'hello-world']
```

> **Note:** parameter patterns match any non-`/` character sequence: `[^/]+`.

---

## Named Routes

Assign a name to the last registered route using `->name()`:

```php
$router->get('/users/{id}', new UserShowHandler())->name('user.show');
$router->post('/users',     new UserCreateHandler())->name('user.create');
```

Retrieve the pattern by name:

```php
$pattern = $router->getNamedRoute('user.show');
// '/users/{id}'
```

Returns `null` if the name does not exist.

---

## Route Groups

Group routes under a shared prefix with `group()`. Groups can be nested.
The prefix is **not** leaked to routes registered after the group.

```php
$router->group('/api', function (Router $r): void {

    $r->get('/users', new UserListHandler());     // matches /api/users
    $r->post('/users', new UserCreateHandler());  // matches /api/users

    $r->group('/admin', function (Router $r): void {
        $r->get('/stats', new StatsHandler());    // matches /api/admin/stats
    });
});

$router->get('/health', new HealthHandler());     // matches /health (no prefix)
```

---

## How the Prefix Index Works

When a route is registered, the router extracts the first path segment and stores
the route index under that key:

| Pattern          | Index key |
|------------------|-----------|
| `/users`         | `users`   |
| `/api/v1/posts`  | `api`     |
| `/`              | `/`       |
| `/{id}`          | `*`       |

During matching, only routes whose first segment matches are considered — avoiding
a full linear scan of all routes on every request.

---

## Error Handling

When `match()` returns `null`, no route matched. Return a 404 response:

```php
$match = $router->match($request->getMethod(), $request->getPath());

if ($match === null) {
    Response::text('Not Found', 404)->send();
    exit;
}
```

Alternatively, use `RouteNotFoundException`:

```php
use LPhenom\Http\Exception\RouteNotFoundException;

if ($match === null) {
    throw new RouteNotFoundException($request->getMethod(), $request->getPath());
}
```

---

## Implementing a Handler

```php
use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

final class PingHandler implements HandlerInterface
{
    public function handle(Request $request): Response
    {
        return Response::json(['pong' => true]);
    }
}
```

Or extend `AbstractController` for convenience helpers:

```php
use LPhenom\Http\Controller\AbstractController;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

final class UserController extends AbstractController
{
    public function handle(Request $request): Response
    {
        return $this->json(['id' => 1, 'name' => 'Alice']);
    }
}
```

