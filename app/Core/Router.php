<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, array $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            if (preg_match($pattern, $requestUri, $matches)) {
                array_shift($matches);
                [$controller, $method] = $route['handler'];

                $controllerInstance = new $controller();
                call_user_func_array([$controllerInstance, $method], $matches);
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
}
