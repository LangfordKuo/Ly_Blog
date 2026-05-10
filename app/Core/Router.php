<?php

namespace LyBlog\Core;

class Router
{
    private $routes = [];
    private $adminPrefix = 'admin';
    private $namespace = 'LyBlog\\Controllers';
    private $middleware = [];
    private $currentGroupPrefix = '';
    private $currentGroupMiddleware = [];

    public function setAdminPrefix(string $prefix): void
    {
        $this->adminPrefix = $prefix;
    }

    public function getAdminPrefix(): string
    {
        return $this->adminPrefix;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = rtrim($namespace, '\\');
    }

    public function group(string $prefix, callable $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix .= $prefix;
        $callback($this);
        $this->currentGroupPrefix = $previousPrefix;
    }

    public function get(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $pattern, $handler, $middleware);
        }
    }

    private function addRoute(string $method, string $pattern, $handler, array $middleware): void
    {
        $pattern = $this->currentGroupPrefix . $pattern;
        $pattern = '/' . trim($pattern, '/');

        $middleware = array_merge($this->currentGroupMiddleware, $middleware);

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
            'regex'      => $this->patternToRegex($pattern),
        ];
    }

    private function patternToRegex(string $pattern): string
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    public function dispatch(Request $request)
    {
        $method = $request->getMethod();
        $path   = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $m) {
                    if (is_callable($m)) {
                        $result = $m($request);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }

                return $this->callHandler($route['handler'], $params, $request);
            }
        }

        http_response_code(404);
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>404</title></head>';
        echo '<body style="font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f7;color:#1d1d1f;">';
        echo '<div style="text-align:center;"><h1 style="font-size:96px;font-weight:200;margin:0;">404</h1><p>页面未找到</p><a href="/" style="color:#1d1d1f;">← 返回首页</a></div>';
        echo '</body></html>';
        return null;
    }

    private function callHandler($handler, array $params, Request $request)
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, array_merge([$request], $params));
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler, 2);
            $class = $this->namespace . '\\' . ltrim($class, '\\');

            if (!class_exists($class)) {
                throw new \RuntimeException("Controller not found: {$class}");
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method not found: {$class}@{$method}");
            }

            return call_user_func_array([$controller, $method], array_merge([$request], $params));
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            return call_user_func_array([$controller, $method], array_merge([$request], $params));
        }

        throw new \RuntimeException('Invalid route handler');
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
