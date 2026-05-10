<?php

namespace LyBlog\Core;

class Request
{
    private $method;
    private $uri;
    private $path;
    private $queryParams;
    private $postData;
    private $segments;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->uri = strtok($this->uri, '?');

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);

        if ($basePath !== '/' && $basePath !== '\\' && strpos($this->uri, $basePath) === 0) {
            $this->path = substr($this->uri, strlen($basePath));
        } else {
            $this->path = $this->uri;
        }

        $this->path = '/' . trim($this->path, '/');
        $this->segments = $this->path === '/' ? [] : explode('/', trim($this->path, '/'));

        $this->queryParams = $_GET;
        $this->postData    = $_POST;
    }

    public function getMethod(): string
    {
        if ($this->method === 'POST' && isset($this->postData['_method'])) {
            return strtoupper($this->postData['_method']);
        }
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function segment(int $index, $default = null): ?string
    {
        return $this->segments[$index] ?? $default;
    }

    public function getQuery(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function getPost(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->postData;
        }
        return $this->postData[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        return $this->postData[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->postData);
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    public function getJson(): array
    {
        if ($this->isJson()) {
            $data = json_decode(file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function isJson(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        return stripos($contentType, 'application/json') !== false;
    }

    public function getIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function getReferer(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }

    public function getBaseUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return rtrim("{$scheme}://{$host}{$base}", '/');
    }

    public function getFullUrl(): string
    {
        return $this->getBaseUrl() . $this->getUri();
    }
}
