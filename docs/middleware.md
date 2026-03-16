# Middleware (Промежуточное ПО)

`lphenom/http` предоставляет простой упорядоченный конвейер middleware с поддержкой короткого замыкания.
Весь middleware совместим с KPHP: без замыканий по ссылке, без reflection.

---

## Контракт MiddlewareInterface

Каждый middleware должен реализовывать `MiddlewareInterface`:

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

Вызовите `$next->handle($request)`, чтобы передать управление следующему middleware (или конечному обработчику).
Верните `Response` напрямую для **короткого замыкания** оставшегося конвейера.

> **Примечание для KPHP:** `__invoke()` НЕ поддерживается в KPHP.  
> Всегда используйте `$next->handle($request)` — **не** `$next($request)`.

---

## Использование MiddlewareStack

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

Middleware выполняется в порядке добавления:

```
Запрос → Middleware A → Middleware B → Обработчик
Ответ  ←             ←              ←
```

---

## Встроенные middleware

### CorsMiddleware

Обрабатывает CORS preflight-запросы (`OPTIONS`) и добавляет заголовки `Access-Control-*`.

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

- Запрос `OPTIONS` → возвращает `204` с CORS-заголовками, обработчик **не вызывается**.
- Остальные методы → CORS-заголовки добавляются к ответу обработчика.
- `allowedOrigins: ['*']` разрешает любой origin (не отправляется при `allowCredentials: true`).
- Запросы с недопустимым origin не получают заголовки `Access-Control-*`.

---

### CsrfMiddleware *(заглушка)*

Проверяет заголовок `X-CSRF-Token` для мутирующих запросов (`POST`, `PUT`, `PATCH`, `DELETE`).

```php
use LPhenom\Http\Middleware\CsrfMiddleware;

$csrf = new CsrfMiddleware(
    secret:    'your-app-secret',
    sessionId: $currentSessionId,
);

// Генерация токена для встраивания в формы / SPA:
$token = $csrf->generateToken($currentSessionId);
```

- `GET`, `HEAD`, `OPTIONS` проходят без проверки.
- Мутирующие запросы без корректного заголовка `X-CSRF-Token` получают ответ `403 Forbidden`.
- Токен формируется как `hmac_sha256($sessionId, $secret)`.

> **TODO:** полное хранилище CSRF-токенов (на основе сессий / паттерн cookie double-submit)
> будет реализовано после появления пакета `lphenom/session`.

---

### RateLimitMiddleware

Делегирует ограничение частоты запросов реализации `RateLimiterInterface` —
сохраняя `lphenom/http` независимым от конкретного кэш-бэкенда.

```php
use LPhenom\Http\Middleware\RateLimitMiddleware;
use LPhenom\Http\Middleware\RateLimiterInterface;

// Реализуйте свой ограничитель на основе Redis / APCu / lphenom/cache:
final class RedisRateLimiter implements RateLimiterInterface
{
    public function isAllowed(string $clientIp): bool
    {
        // увеличиваем счётчик в Redis, возвращаем false при превышении лимита
        return true;
    }
}

$stack->add(new RateLimitMiddleware(new RedisRateLimiter()));
```

Возвращает `429 Too Many Requests`, когда `isAllowed()` возвращает `false`.

---

## Написание собственного middleware

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
            // Короткое замыкание: возвращаем ответ немедленно без вызова $next
            return Response::text('Unauthorized', 401);
        }

        // Продолжаем конвейер
        return $next->handle($request);
    }
}
```

---

## Next / Короткое замыкание

`Next` — это класс с состоянием, который продвигает конвейер на один шаг при каждом вызове.
Это **не** замыкание — совместимо с KPHP.

> **Примечание для KPHP:** используйте `$next->handle($request)` — НЕ `$next($request)`.  
> `__invoke()` не поддерживается в KPHP.

```
$next->handle($request)   — вызывает следующий middleware или конечный обработчик
return $response          — короткое замыкание: оставшиеся middleware и обработчик пропускаются
```

Модель выполнения (три middleware, один обработчик):

```
→ M1.process()
    → $next->handle($request)          // переходит к M2
        → M2.process()
            → $next->handle($request)  // переходит к M3
                → M3.process()
                    → $next->handle($request)  // вызывает обработчик
                    ← Response
                ← Response
            ← Response
        ← Response
    ← Response
← Response
```

Если `M2` возвращает ответ досрочно без вызова `$next->handle()`, то `M3` и обработчик не вызываются.

---

## Совместное использование Router + MiddlewareStack

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
