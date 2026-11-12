<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Request Library
 * Encapsulates HTTP Request details, inputs, headers, and files.
 */
class Request
{
    private array $getParams = [];
    private array $postParams = [];
    private array $routeParams = [];
    private ?array $jsonBody = null;
    private array $files = [];
    private array $server = [];

    public function __construct(array $routeParams = [], ?array $server = null, ?array $post = null, ?array $get = null)
    {
        $this->getParams = $get ?? $_GET;
        $this->postParams = $post ?? $_POST;
        $this->files = $_FILES;
        $this->server = $server ?? $_SERVER;
        $this->routeParams = $routeParams;

        // Parse JSON payload jika Content-Type application/json
        $contentType = $this->header('Content-Type') ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            $decoded = json_decode($rawInput, true);
            if (is_array($decoded)) {
                $this->jsonBody = $decoded;
            }
        }
    }

    /**
     * Ambil HTTP Method request
     */
    public function getMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST' && isset($this->postParams['_method'])) {
            $method = strtoupper($this->postParams['_method']);
        }
        return strtoupper($method);
    }

    /**
     * Ambil path URI yang diminta
     */
    public function getPath(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Normalize jika script berjalan di subdirektori (misal /PRD/public atau /PRD melalui root .htaccess rewrite)
        $scriptName = $this->server['SCRIPT_NAME'] ?? '';
        $baseDir = str_replace('\\', '/', dirname($scriptName));
        $baseDir = rtrim($baseDir, '/');

        if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        } else {
            $projectDir = str_replace('\\', '/', dirname($baseDir));
            $projectDir = rtrim($projectDir, '/');
            if ($projectDir !== '' && $projectDir !== '/' && str_starts_with($path, $projectDir)) {
                $path = substr($path, strlen($projectDir));
            }
        }

        $path = trim($path, '/');
        return '/' . $path;
    }

    /**
     * Ambil parameter dari GET / Query string
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getParams[$key] ?? $default;
    }

    /**
     * Ambil seluruh atau satu parameter GET
     */
    public function getGet(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->getParams;
        }
        return $this->getParams[$key] ?? $default;
    }

    /**
     * Ambil parameter dari POST / Form data
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->postParams[$key] ?? $default;
    }

    /**
     * Ambil seluruh atau satu parameter POST
     */
    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postParams;
        }
        return $this->postParams[$key] ?? $default;
    }

    /**
     * Ambil parameter dari URL route (misal {id})
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Ambil input dari JSON, POST, atau GET
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->jsonBody !== null && array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }
        if (array_key_exists($key, $this->postParams)) {
            return $this->postParams[$key];
        }
        if (array_key_exists($key, $this->getParams)) {
            return $this->getParams[$key];
        }
        if (array_key_exists($key, $this->routeParams)) {
            return $this->routeParams[$key];
        }
        return $default;
    }

    /**
     * Ambil seluruh input request yang digabung
     */
    public function all(): array
    {
        return array_merge(
            $this->getParams,
            $this->postParams,
            $this->jsonBody ?? [],
            $this->routeParams
        );
    }

    /**
     * Ambil file yang diunggah
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Ambil Header request
     */
    public function header(string $name): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$normalized])) {
            return $this->server[$normalized];
        }
        if (isset($this->server[strtoupper($name)])) {
            return $this->server[strtoupper($name)];
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, $name) === 0) {
                    return $v;
                }
            }
        }
        return null;
    }

    /**
     * Deteksi apakah request berasal dari AJAX / Fetch API
     */
    public function isAjax(): bool
    {
        $requestedWith = $this->header('X-Requested-With');
        $accept = $this->header('Accept') ?? '';

        return ($requestedWith === 'XMLHttpRequest') || str_contains($accept, 'application/json');
    }

    /**
     * Ambil IP Address klien
     */
    public function getIp(): string
    {
        return $this->server['HTTP_CLIENT_IP']
            ?? $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '127.0.0.1';
    }

    /**
     * Ambil User Agent browser
     */
    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? 'Unknown';
    }
}
