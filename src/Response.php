<?php

declare(strict_types=1);

namespace LPhenom\Http;

/**
 * HTTP Response.
 *
 * Immutable via withX() methods returning new instances.
 * KPHP-compatible: no reflection, no magic, no clone, no constructor property promotion.
 */
final class Response
{
    /** @var int */
    private int $status;

    /** @var array<string, string> */
    private array $headers;

    /** @var string */
    private string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        string $body = ''
    ) {
        $this->status  = $status;
        $this->headers = $headers;
        $this->body    = $body;
    }

    public function getStatus(): int
    {
        return $this->status;
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

    public function getBody(): string
    {
        return $this->body;
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->headers, $this->body);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers       = $this->headers;
        $headers[$name] = $value;
        return new self($this->status, $headers, $this->body);
    }

    public function withBody(string $body): self
    {
        return new self($this->status, $this->headers, $body);
    }

    /**
     * Create a JSON response.
     *
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        // JSON_THROW_ON_ERROR is not supported in KPHP — check manually
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{}';
        }
        /** @var array<string, string> $headers */
        $headers = ['Content-Type' => 'application/json'];
        return new self($status, $headers, $encoded);
    }

    /**
     * Create a plain-text response.
     */
    public static function text(string $text, int $status = 200): self
    {
        /** @var array<string, string> $headers */
        $headers = ['Content-Type' => 'text/plain; charset=UTF-8'];
        return new self($status, $headers, $text);
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $status = 302): self
    {
        /** @var array<string, string> $headers */
        $headers = ['Location' => $url];
        return new self($status, $headers, '');
    }

    /**
     * Send the response to the client.
     *
     * KPHP note: http_response_code() is not supported in KPHP.
     * Use header() with the HTTP status line instead.
     */
    public function send(): void
    {
        // Set HTTP status line (KPHP does not have http_response_code())
        header('HTTP/1.1 ' . $this->status . ' ' . $this->reasonPhrase($this->status), true, $this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }

    /**
     * Map common HTTP status codes to reason phrases.
     * KPHP-safe: no match expression (PHP 8.0), uses if/elseif chain.
     */
    private function reasonPhrase(int $status): string
    {
        if ($status === 200) {
            return 'OK';
        }
        if ($status === 201) {
            return 'Created';
        }
        if ($status === 204) {
            return 'No Content';
        }
        if ($status === 301) {
            return 'Moved Permanently';
        }
        if ($status === 302) {
            return 'Found';
        }
        if ($status === 400) {
            return 'Bad Request';
        }
        if ($status === 401) {
            return 'Unauthorized';
        }
        if ($status === 403) {
            return 'Forbidden';
        }
        if ($status === 404) {
            return 'Not Found';
        }
        if ($status === 405) {
            return 'Method Not Allowed';
        }
        if ($status === 422) {
            return 'Unprocessable Entity';
        }
        if ($status === 429) {
            return 'Too Many Requests';
        }
        if ($status === 500) {
            return 'Internal Server Error';
        }
        return 'Unknown';
    }
}
