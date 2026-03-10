<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Callback interface for Router::group().
 *
 * KPHP does not support storing \Closure in typed arrays or using them
 * as typed parameters reliably. This interface provides a KPHP-safe
 * alternative to anonymous functions for route group registration.
 *
 * Usage:
 *   $router->group('/api', new class implements RouterGroupCallback {
 *       public function call(Router $router): void {
 *           $router->get('/users', new UsersHandler());
 *       }
 *   });
 */
interface RouterGroupCallback
{
    public function call(Router $router): void;
}
