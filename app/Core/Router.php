<?php

namespace App\Core;

use App\Config\Environment;
use App\Middleware\SecurityHeadersMiddleware;

class Router
{
    private array $routes = [];

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
        SecurityHeadersMiddleware::apply();

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        // Crawlers and monitors use HEAD. Serve the same route as GET.
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUri = is_string($requestUri) ? $requestUri : '/';

        foreach ($this->uriPrefixesToStrip() as $prefix) {
            if ($prefix !== '' && strpos($requestUri, $prefix) === 0) {
                $requestUri = substr($requestUri, strlen($prefix));
                break;
            }
        }

        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/' . ltrim($requestUri, '/');
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
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo "404 Not Found";
    }

    /**
     * Prefixes to remove so /login matches after shared-hosting rewrites into /public.
     *
     * @return list<string>
     */
    private function uriPrefixesToStrip(): array
    {
        $prefixes = [];

        $appUrl = trim((string) Environment::get('APP_URL', ''));
        $urlPath = $appUrl !== '' ? rtrim((string) (parse_url($appUrl, PHP_URL_PATH) ?: ''), '/') : '';
        if ($urlPath !== '' && $urlPath !== '/') {
            $prefixes[] = $urlPath;
        }

        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.' && !in_array($scriptDir, $prefixes, true)) {
            $prefixes[] = $scriptDir;
        }

        return $prefixes;
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
