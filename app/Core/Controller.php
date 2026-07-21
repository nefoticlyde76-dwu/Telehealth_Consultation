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
}
