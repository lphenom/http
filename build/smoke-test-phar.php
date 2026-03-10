#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading works.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-http.phar
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-http.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, 'PHAR not found: ' . $pharFile . PHP_EOL);
    exit(1);
}

require $pharFile;

// Verify Request instantiation
$request = new \LPhenom\Http\Request('GET', '/api/users', [], [], [], '', [], '127.0.0.1');
assert($request->getMethod() === 'GET', 'Request method failed');
assert($request->getPath() === '/api/users', 'Request path failed');
echo 'smoke-test: Request ok' . PHP_EOL;

// Verify Response helpers
$response = \LPhenom\Http\Response::json(['status' => 'ok']);
assert($response->getStatus() === 200, 'Response status failed');
assert($response->getHeader('Content-Type') === 'application/json', 'Response content-type failed');
echo 'smoke-test: Response ok' . PHP_EOL;

// Verify Router basic matching
$router = new \LPhenom\Http\Router();

$handler = new class () implements \LPhenom\Http\HandlerInterface {
    public function handle(\LPhenom\Http\Request $request): \LPhenom\Http\Response
    {
        return \LPhenom\Http\Response::text('ok');
    }
};

$router->get('/ping', $handler);
$match = $router->match('GET', '/ping');
assert($match !== null, 'Router match failed');
echo 'smoke-test: Router ok' . PHP_EOL;

// Verify MiddlewareStack
$stack = new \LPhenom\Http\MiddlewareStack();
$resp = $stack->run($request, $handler);
assert($resp->getStatus() === 200, 'MiddlewareStack failed');
echo 'smoke-test: MiddlewareStack ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

