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
}
