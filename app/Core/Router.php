<?php

namespace App\Core;

use App\Config\Environment;

class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct()
    {
        $appUrl = Environment::get('APP_URL', 'http://localhost');
        $this->basePath = parse_url($appUrl, PHP_URL_PATH) ?: '';
    }

    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Strip base path from request URI
        if ($this->basePath && strpos($requestUri, $this->basePath) === 0) {
            $requestUri = substr($requestUri, strlen($this->basePath));
        }

        // Ensure the URI starts with '/' and is at least '/'
        if (empty($requestUri) || $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $requestUri, $matches)) {
                $parameters = [];

                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $parameters[] = $value;
                    }
                }

                if ($parameters === []) {
                    array_shift($matches);
                    $parameters = array_values($matches);
                }

                $this->runMiddleware($route['middleware'] ?? []);
                [$controller, $method] = $route['handler'];

                $controllerInstance = new $controller();
                call_user_func_array([$controllerInstance, $method], $parameters);
                return;
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function runMiddleware(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (is_string($middleware)) {
                $middleware = new $middleware();
            }

            if (is_callable($middleware)) {
                $middleware();
                continue;
            }

            if (is_object($middleware) && method_exists($middleware, 'handle')) {
                $middleware->handle();
            }
        }
    }
}
