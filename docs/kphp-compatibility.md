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
    public function __construct(private readonly HandlerInterface $handler) {}

    public function call(Router $r): void
    {
        $r->get('/users', $this->handler);
    }
});
```

---

## Запрещённые конструкции (не поддерживаются KPHP)

| Конструкция | Замена |
|---|---|
| `$next($request)` — `__invoke()` | `$next->handle($request)` |
| `\Closure` в group() | `RouterGroupCallback` interface |
| `str_starts_with()` | `substr($s, 0, N) === 'prefix'` |
| `str_ends_with()` | `substr($s, -N) === 'suffix'` |
| `str_contains()` | `strpos($s, 'needle') !== false` |
| `JSON_THROW_ON_ERROR` | ручная проверка `json_encode() === false` |
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
| `readonly` constructor promotion | ✅ (PHP 8.1) |
| `?Type` nullable types | ✅ |
| `int\|string` union types | ✅ |
| `array<K, V>` в PHPDoc | ✅ |
| `new ClassName()` — явный | ✅ |
| `try/catch` (с хотя бы одним catch) | ✅ |
| `clone $this` (в Response::with*()) | ✅ |
| `instanceof` | ✅ |
| `substr()`, `strpos()`, `strlen()` | ✅ |
| `json_encode()`, `json_decode()` | ✅ |
| `preg_match()`, `preg_split()` | ✅ |
| `hash_hmac()`, `hash_equals()` | ✅ |
| `implode()`, `explode()` | ✅ |
| `array_merge()`, `in_array()` | ✅ |
| `strtoupper()`, `strtolower()` | ✅ |

---

## KPHP entrypoint

KPHP **не поддерживает** Composer PSR-4 autoloading.  
Все файлы включаются явно через `build/kphp-entrypoint.php` в порядке зависимостей:

```
Interfaces → Exceptions → Value objects → Next → Router → Stack → Middleware → Controllers
```

---

## Проверка совместимости

```bash
# Собрать и проверить KPHP binary + PHAR
make kphp-check
# или напрямую:
docker build -f Dockerfile.check -t lphenom-http-check .
```

Обе стадии (`kphp-build` и `phar-build`) должны завершиться с кодом 0.

---

## Ссылки

- [KPHP Documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP differences](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [KPHP Docker image](https://hub.docker.com/r/vkcom/kphp)
- [docs/routing.md](./routing.md)
- [docs/middleware.md](./middleware.md)

