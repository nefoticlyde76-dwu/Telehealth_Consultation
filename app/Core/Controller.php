<?php

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';

        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new \RuntimeException('View not found: ' . $view);
        }
    }

    protected function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        extract($data);
        ob_start();
        $this->view($view, $data);
        $content = ob_get_clean();

        $this->view($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Emit a JSON response with an explicit HTTP status code.
     *
     * Used by Week 6 video consultation join-token endpoints (and any
     * future API-style routes) instead of mixing echo statements inside
     * controller methods directly.
     *
     * Content-Type is always application/json; charset=utf-8.
     * Body is always a JSON object (never a raw scalar) so callers can
     * consistently introspect {message, code, data, ...}.
     *
     * @param array<int|string, mixed> $payload
     */
    protected function jsonResponse(array $payload, int $httpCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            if ($httpCode >= 100 && $httpCode <= 599) {
                http_response_code($httpCode);
            }
        }

        echo json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
