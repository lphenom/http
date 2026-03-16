# lphenom/http
[![CI](https://github.com/lphenom/http/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/http/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

HTTP-пакет LPhenom — примитивы запроса/ответа, роутер и конвейер middleware.
Совместим с KPHP: без eval, без reflection, без динамической загрузки классов.

## Возможности

- **Request** — method, path, query, headers, cookies, body, files, clientIp, json(), queryString()
- **Response** — иммутабельный через withX(), вспомогательные методы: json(), text(), redirect()
- **Router** — оптимизирован по префиксному индексу, именованные маршруты, группы маршрутов, хелперы HTTP-методов
- **MiddlewareStack** — упорядоченный конвейер с поддержкой короткого замыкания
- **Security middleware** — CorsMiddleware, CsrfMiddleware, RateLimitMiddleware
- **AbstractController** — базовый класс с вспомогательными методами json(), redirect(), requireAuth()

## Требования

- PHP 8.1+
- lphenom/core 0.1.0

## Установка

```bash
composer require lphenom/http
```

## Разработка (Docker)

Весь инструментарий запускается внутри Docker — локальные PHP и Composer не требуются.

```bash
make up      # собрать и запустить контейнеры
make install # установить зависимости
make test    # запустить PHPUnit
make lint    # проверить стиль кода
make fix     # автоматически исправить стиль кода
make stan    # запустить PHPStan
make shell   # открыть bash в контейнере
make down    # остановить контейнеры
```

## Документация

- [docs/routing.md](docs/routing.md) — маршрутизация
- [docs/middleware.md](docs/middleware.md) — middleware
- [docs/kphp-compatibility.md](docs/kphp-compatibility.md) — совместимость с KPHP

## Лицензия

MIT — см. [LICENSE](LICENSE).
