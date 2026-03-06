<?php

declare(strict_types=1);

namespace LPhenom\Http\Controller;

use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

/**
 * Base controller with convenience helpers.
 *
 * Subclasses must implement handle(Request): Response.
 *
 * KPHP-compatible: no magic, no reflection.
 *
 * Example:
 *   final class UserController extends AbstractController
 *   {
 *       public function handle(Request $request): Response
 *       {
 *           return $this->json(['id' => 1, 'name' => 'Alice']);
 *       }
 *   }
 */
abstract class AbstractController implements HandlerInterface
{
    /**
     * Return a JSON response.
     *
     * @param array<string, mixed> $data
     */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Return a redirect response.
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Assert that the request is authenticated.
     *
     * TODO: integrate with lphenom/auth when available.
     * Currently throws RuntimeException as a stub.
     *
     * @throws \RuntimeException when request is not authenticated
     */
    protected function requireAuth(Request $request): void
    {
        // Stub: check for Authorization header presence
        $auth = $request->getHeader('Authorization');
        if ($auth === null || $auth === '') {
            throw new \RuntimeException('Unauthorized', 401);
        }
    }
}

