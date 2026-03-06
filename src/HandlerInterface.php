<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Handler contract: receives a Request and returns a Response.
 *
 * KPHP-compatible interface — no generics, no reflection.
 */
interface HandlerInterface
{
    public function handle(Request $request): Response;
}

