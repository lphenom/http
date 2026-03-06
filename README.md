# lphenom/http
[![CI](https://github.com/lphenom/http/actions/workflows/ci.yml/badge.svg)](https://github.com/lphenom/http/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
LPhenom HTTP package — request/response primitives, router, and middleware pipeline.
KPHP-compatible: no eval, no reflection, no dynamic class loading.
## Features
- Request — method, path, query, headers, cookies, body, files, clientIp, json(), queryString()
- Response — immutable via withX(), helpers: json(), text(), redirect()
- Router — prefix-index optimised, named routes, route groups, HTTP method helpers
- MiddlewareStack — ordered pipeline with short-circuit support
- Security middleware stubs — CorsMiddleware, CsrfMiddleware, RateLimitMiddleware
- AbstractController — base class with json(), redirect(), requireAuth() helpers
## Requirements
- PHP 8.1+
- lphenom/core ^0.1
## Installation
```bash
composer require lphenom/http
```
## Development (Docker)
All tooling runs inside Docker — no local PHP or Composer required.
```bash
make up      # build & start containers
make install # install dependencies
make test    # run PHPUnit
make lint    # check code style
make fix     # auto-fix code style
make stan    # run PHPStan
make shell   # open bash in container
make down    # stop containers
```
## Documentation
- docs/routing.md
- docs/middleware.md
## License
MIT — see LICENSE.
