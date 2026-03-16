# Маршрутизация

`lphenom/http` предоставляет быстрый роутер с префиксным индексом, поддержкой статических и динамических маршрутов,
именованных маршрутов и групп маршрутов.

---

## Быстрый старт

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
$match = $router->match($request->getMethod(), $request->getPath());

if ($match === null) {
    Response::text('Not Found', 404)->send();
    exit;
}

$match->handler->handle($request)->send();
```

---

## Регистрация маршрутов

### Вспомогательные методы HTTP

```php
$router->get('/users',        new UserListHandler());
$router->post('/users',       new UserCreateHandler());
$router->put('/users/{id}',   new UserUpdateHandler());
$router->patch('/users/{id}', new UserPatchHandler());
$router->delete('/users/{id}',new UserDeleteHandler());
```

### Универсальный `add()`

```php
$router->add('GET', '/ping', new PingHandler());
```

Названия методов нечувствительны к регистру — `GET`, `get`, `Get` — всё равнозначно.

---

## Параметры маршрута

Сегменты в фигурных скобках `{name}` становятся именованными параметрами, извлекаемыми при совпадении.

```php
$router->get('/users/{id}',           new UserShowHandler());
$router->get('/posts/{year}/{slug}',   new PostShowHandler());
```

Параметры доступны в `$match->params`:

```php
$match = $router->match('GET', '/users/42');
// $match->params === ['id' => '42']

$match = $router->match('GET', '/posts/2024/hello-world');
// $match->params === ['year' => '2024', 'slug' => 'hello-world']
```

> **Примечание:** параметры совпадают с любой последовательностью символов, кроме `/`: `[^/]+`.

---

## Именованные маршруты

Присвойте имя последнему зарегистрированному маршруту через `->name()`:

```php
$router->get('/users/{id}', new UserShowHandler())->name('user.show');
$router->post('/users',     new UserCreateHandler())->name('user.create');
```

Получение шаблона по имени:

```php
$pattern = $router->getNamedRoute('user.show');
// '/users/{id}'
```

Возвращает `null`, если имя не существует.

---

## Группы маршрутов

Группируйте маршруты под общим префиксом с помощью `group()`. Группы можно вкладывать.
Префикс **не применяется** к маршрутам, зарегистрированным после группы.

> **Примечание для KPHP:** `\Closure` не поддерживается как типизированный callback в KPHP.  
> Используйте интерфейс `RouterGroupCallback` вместо анонимных функций.

```php
use LPhenom\Http\RouterGroupCallback;

// PHP 8.1+ режим shared hosting (Closure допустима для не-KPHP)
// Для скомпилированного KPHP-бинарника используйте RouterGroupCallback:

$router->group('/api', new class implements RouterGroupCallback {
    public function call(Router $r): void
    {
        $r->get('/users', new UserListHandler());    // совпадает с /api/users
        $r->post('/users', new UserCreateHandler()); // совпадает с /api/users

        $r->group('/admin', new class implements RouterGroupCallback {
            public function call(Router $r): void
            {
                $r->get('/stats', new StatsHandler()); // совпадает с /api/admin/stats
            }
        });
    }
});

$router->get('/health', new HealthHandler());     // совпадает с /health (без префикса)
```

---

## Как работает префиксный индекс

При регистрации маршрута роутер извлекает первый сегмент пути и хранит
индекс маршрута под этим ключом:

| Шаблон           | Ключ индекса |
|------------------|--------------|
| `/users`         | `users`      |
| `/api/v1/posts`  | `api`        |
| `/`              | `/`          |
| `/{id}`          | `*`          |

При совпадении рассматриваются только маршруты, у которых совпадает первый сегмент —
это позволяет избежать полного перебора всех маршрутов при каждом запросе.

---

## Обработка ошибок

Когда `match()` возвращает `null`, ни один маршрут не совпал. Верните ответ 404:

```php
$match = $router->match($request->getMethod(), $request->getPath());

if ($match === null) {
    Response::text('Not Found', 404)->send();
    exit;
}
```

Альтернативно используйте `RouteNotFoundException`:

```php
use LPhenom\Http\Exception\RouteNotFoundException;

if ($match === null) {
    throw new RouteNotFoundException($request->getMethod(), $request->getPath());
}
```

---

## Реализация обработчика

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

Или расширьте `AbstractController` для удобных вспомогательных методов:

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
