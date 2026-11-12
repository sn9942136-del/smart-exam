<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Response Library
 * Manages HTTP Response headers, status codes, JSON formatting, and redirects.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set format response sebagai JSON
     */
    public function json(mixed $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $this;
    }

    /**
     * Redirect ke URL tujuan
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        return $this;
    }

    /**
     * Kirimkan header dan konten ke browser/klien
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }
}
