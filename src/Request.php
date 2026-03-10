<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * Immutable HTTP Request.
 *
 * KPHP-compatible: no reflection, no dynamic properties, no variable variables.
 */
final class Request
{
    /**
     * @param array<string, string>       $query
     * @param array<string, string>       $headers
     * @param array<string, string>       $cookies
     * @param array<string, mixed>        $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $headers,
        private readonly array $cookies,
        private readonly string $body,
        private readonly array $files,
        private readonly string $clientIp,
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    public function getClientIp(): string
    {
        return $this->clientIp;
    }

    public function queryString(): string
    {
        if ($this->query === []) {
            return '';
        }
        return http_build_query($this->query);
    }

    /**
     * Decode JSON body.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException if body is not valid JSON
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($this->body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON body: ' . json_last_error_msg());
        }

        if (!\is_array($decoded)) {
            throw new \RuntimeException('JSON body must be an object or array, got scalar');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Create Request from PHP superglobals.
     * Safe for KPHP: iterates $_SERVER with string key checks, no variable variables.
     */
    public static function fromGlobals(): self
    {
        $method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';

        $path = '/';
        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $parsed = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            if (is_string($parsed)) {
                $path = $parsed;
            }
        }

        /** @var array<string, string> $query */
        $query = [];
        foreach ($_GET as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $query[$k] = $v;
            }
        }

        // Build headers from $_SERVER HTTP_* keys (no getallheaders() for KPHP portability)
        /** @var array<string, string> $headers */
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (substr($key, 0, 5) === 'HTTP_') {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            } elseif ($key === 'CONTENT_TYPE') {
                $headers['CONTENT-TYPE'] = $value;
            } elseif ($key === 'CONTENT_LENGTH') {
                $headers['CONTENT-LENGTH'] = $value;
            }
        }

        /** @var array<string, string> $cookies */
        $cookies = [];
        foreach ($_COOKIE as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $cookies[$k] = $v;
            }
        }

        $rawBody = file_get_contents('php://input');
        $body = $rawBody !== false ? $rawBody : '';

        /** @var array<string, mixed> $files */
        $files = [];
        foreach ($_FILES as $k => $v) {
            if (is_string($k)) {
                $files[$k] = $v;
            }
        }

        $clientIp = '127.0.0.1';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $clientIp = trim($parts[0]);
        } elseif (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            $clientIp = $_SERVER['REMOTE_ADDR'];
        }

        return new self($method, $path, $query, $headers, $cookies, $body, $files, $clientIp);
    }
}
