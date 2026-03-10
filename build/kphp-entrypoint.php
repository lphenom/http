<?php

/**
 * KPHP entrypoint for lphenom/http.
 *
 * KPHP does NOT support Composer PSR-4 autoloading.
 * All source files must be explicitly require_once'd in dependency order:
 * interfaces and exceptions first, then concrete classes.
 *
 * Compile with:
 *   kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
 */

declare(strict_types=1);

// ---- Interfaces (no dependencies) ----
require_once __DIR__ . '/../src/HandlerInterface.php';
require_once __DIR__ . '/../src/RouterGroupCallback.php';
require_once __DIR__ . '/../src/Middleware/RateLimiterInterface.php';

// ---- Exceptions ----
require_once __DIR__ . '/../src/Exception/RouteNotFoundException.php';

// ---- Core value objects ----
require_once __DIR__ . '/../src/Request.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/RouteMatch.php';

// ---- Next (depends on HandlerInterface, MiddlewareInterface — declared below) ----
// MiddlewareInterface must come before Next
require_once __DIR__ . '/../src/MiddlewareInterface.php';
require_once __DIR__ . '/../src/Next.php';

// ---- Router ----
require_once __DIR__ . '/../src/Router.php';

// ---- Middleware stack ----
require_once __DIR__ . '/../src/MiddlewareStack.php';

// ---- Middleware implementations ----
require_once __DIR__ . '/../src/Middleware/CorsMiddleware.php';
require_once __DIR__ . '/../src/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/../src/Middleware/RateLimitMiddleware.php';

// ---- Controller ----
require_once __DIR__ . '/../src/Controller/AbstractController.php';

// ---- Smoke-check: instantiate key classes ----
$request = new \LPhenom\Http\Request('GET', '/', [], [], [], '', [], '127.0.0.1');
$response = \LPhenom\Http\Response::text('kphp-check ok', 200);
$router = new \LPhenom\Http\Router();

echo 'lphenom/http KPHP entrypoint OK' . PHP_EOL;

