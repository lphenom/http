# KPHP Compatibility Guide — lphenom/http

Этот документ описывает **все ограничения и правила KPHP-совместимости** для пакета `lphenom/http`.

---

## Обзор

`lphenom/http` совместим с [KPHP](https://vkcom.github.io/kphp/) — компилятором PHP → C++.  
Скомпилированный бинарник работает без PHP runtime.

---

## Ключевые изменения в API для KPHP

### `Next::handle()` вместо `$next()`

KPHP **не поддерживает `__invoke()`** как callable-вызов объекта.

```php
// ❌ НЕ РАБОТАЕТ в KPHP
$response = $next($request);

// ✅ ПРАВИЛЬНО — явный вызов метода
$response = $next->handle($request);
```

### `RouterGroupCallback` вместо `\Closure`

KPHP не поддерживает `\Closure` как тип параметра, который используется
в контексте хранения в массивах или передачи в typed-контексте.

```php
// ❌ НЕ РАБОТАЕТ в KPHP
$router->group('/api', function (Router $r): void {
    $r->get('/users', $handler);
});

// ✅ ПРАВИЛЬНО — реализуйте RouterGroupCallback
$router->group('/api', new class ($handler) implements RouterGroupCallback {
    private HandlerInterface $handler;
    public function __construct(HandlerInterface $handler) {
        $this->handler = $handler;
    }
    public function call(Router $r): void
    {
        $r->get('/users', $this->handler);
    }
});
```

> **Примечание:** в `Router::group()` callback вызывается немедленно и **не сохраняется**
> в массиве — это единственная причина, по которой передача `RouterGroupCallback`
> не нарушает ограничение KPHP на хранение объектов в typed-массивах.

### Без constructor property promotion и без `readonly`

KPHP не поддерживает constructor property promotion и `readonly` свойства.
Все свойства объявляются явно.

```php
// ❌ НЕ РАБОТАЕТ в KPHP
final class Request {
    public function __construct(
        private readonly string $method,
        private readonly string $path,
    ) {}
}

// ✅ ПРАВИЛЬНО — явное объявление свойств
final class Request {
    /** @var string */
    private string $method;
    /** @var string */
    private string $path;

    public function __construct(string $method, string $path) {
        $this->method = $method;
        $this->path   = $path;
    }
}
```

### `match` expression не поддерживается

KPHP не поддерживает выражение `match`. Используйте `if/elseif`-цепочку.

```php
// ❌ НЕ РАБОТАЕТ в KPHP
$phrase = match($status) {
    200 => 'OK',
    404 => 'Not Found',
    default => 'Unknown',
};

// ✅ ПРАВИЛЬНО — if/elseif
if ($status === 200) { $phrase = 'OK'; }
elseif ($status === 404) { $phrase = 'Not Found'; }
else { $phrase = 'Unknown'; }
```

### `json_last_error()` / `json_last_error_msg()` не поддерживаются

```php
// ❌ НЕ РАБОТАЕТ в KPHP
$data = json_decode($body, true);
if (json_last_error() !== JSON_ERROR_NONE) { ... }

// ✅ ПРАВИЛЬНО — проверяем null
$data = json_decode($body, true);
if ($data === null) {
    throw new \RuntimeException('Invalid JSON body');
}
```

### `http_response_code()` не поддерживается

```php
// ❌ НЕ РАБОТАЕТ в KPHP
http_response_code(404);

// ✅ ПРАВИЛЬНО — используем header() с HTTP-строкой статуса
header('HTTP/1.1 404 Not Found', true, 404);
```

### `JSON_THROW_ON_ERROR` не поддерживается

```php
// ❌ НЕ РАБОТАЕТ в KPHP
$json = json_encode($data, JSON_THROW_ON_ERROR);

// ✅ ПРАВИЛЬНО — проверяем false вручную
$json = json_encode($data, JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $json = '{}';
}
```

---

## Запрещённые конструкции (не поддерживаются KPHP)

| Конструкция | Замена |
|---|---|
| `$next($request)` — `__invoke()` | `$next->handle($request)` |
| `\Closure` в group() | `RouterGroupCallback` interface |
| `readonly` properties / constructor promotion | явное объявление свойств и конструктора |
| `match($x) { ... }` expression | `if/elseif` цепочка |
| `str_starts_with()` | `substr($s, 0, N) === 'prefix'` |
| `str_ends_with()` | `substr($s, -N) === 'suffix'` |
| `str_contains()` | `strpos($s, 'needle') !== false` |
| `JSON_THROW_ON_ERROR` | ручная проверка `json_encode() === false` |
| `json_last_error()` / `json_last_error_msg()` | проверка `json_decode() === null` |
| `http_response_code()` | `header('HTTP/1.1 ...')` |
| `PREG_SPLIT_DELIM_CAPTURE` | ручной парсинг строки |
| `array{key: type}` shape PHPDoc | `array<string, mixed>` |
| `new $className()` | явный `new MyClass()` |
| `Reflection*` | явные фабрики |
| `eval()` | — |
| `$$varName` | явные переменные |

---

## Разрешённые конструкции

| Конструкция | Статус |
|---|---|
| `declare(strict_types=1)` | ✅ |
| `final class`, `interface`, `abstract class` | ✅ |
| `?Type` nullable types | ✅ |
| `int\|string` union types | ✅ |
| `array<K, V>` в PHPDoc | ✅ |
| `new ClassName()` — явный | ✅ |
| `try/catch` (с хотя бы одним catch) | ✅ |
| `instanceof` | ✅ |
| `substr()`, `strpos()`, `strlen()` | ✅ |
| `json_encode()`, `json_decode()` | ✅ |
| `preg_match()` | ✅ |
| `hash_hmac()` | ✅ |
| `hash_equals()` | ✅ (поддерживается с KPHP builtin-functions) |
| `implode()`, `explode()` | ✅ |
| `array_merge()`, `in_array()` | ✅ |
| `strtoupper()`, `strtolower()` | ✅ |
| `http_build_query()` | ✅ |
| `parse_url()` | ✅ |
| `header()` | ✅ |
| `file_get_contents('php://input')` | ✅ |
| `echo` | ✅ |

---

## Типизация массивов

KPHP строго проверяет однородность типов в массивах.

```php
// ❌ Нельзя хранить разнотипные объекты в одном массиве
$items = [$handler, $middleware]; // HandlerInterface и MiddlewareInterface — разные типы

// ✅ Параллельные массивы с явными типами
/** @var HandlerInterface[] */
private array $handlers = [];
/** @var MiddlewareInterface[] */
private array $middleware = [];
```

Router использует именно параллельные массивы (`$routeMethods`, `$routePatterns`,
`$routeRegexes`, `$routeParams`, `$routeHandlers`) вместо массива объектов-маршрутов.

---

## KPHP entrypoint

KPHP **не поддерживает** Composer PSR-4 autoloading.  
Все файлы включаются явно через `build/kphp-entrypoint.php` в порядке зависимостей:

```
HandlerInterface
  → RouteMatch
  → Request, Response
  → MiddlewareInterface → Next
  → Router              ← должен быть ДО RouterGroupCallback!
  → RouterGroupCallback ← ссылается на Router
  → RateLimiterInterface
  → RouteNotFoundException
  → MiddlewareStack
  → CorsMiddleware, CsrfMiddleware, RateLimitMiddleware
  → AbstractController
```

> **⚠️ Важно для vendor-использования:** `RouterGroupCallback` ссылается на `Router`
> в сигнатуре метода `call(Router $router): void`.  
> Поэтому `Router.php` **обязан** быть загружен раньше `RouterGroupCallback.php`.  
> Если в вашем kphp-entrypoint файлы vendor грузятся в алфавитном порядке,
> убедитесь что `Router.php` стоит перед `RouterGroupCallback.php`.

Порядок важен: `MiddlewareInterface` должен быть объявлен **до** `Next`, который его использует.

---

## Проверка совместимости

```bash
# Собрать и проверить KPHP binary + PHAR (через Makefile)
make kphp-check

# или напрямую:
docker build -f Dockerfile.check -t lphenom-http-check .
```

`Dockerfile.check` содержит два stage:

- **`kphp-build`** — компилирует `build/kphp-entrypoint.php` через `vkcom/kphp:latest`
  и запускает полученный бинарник
- **`phar-build`** — собирает PHAR через PHP 8.1 и запускает smoke-test

Оба stage должны завершиться с кодом 0.

---

## Ссылки

- [KPHP Documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [KPHP Docker image](https://hub.docker.com/r/vkcom/kphp)
- [docs/routing.md](./routing.md)
- [docs/middleware.md](./middleware.md)

