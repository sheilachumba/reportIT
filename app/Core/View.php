<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private array $config)
    {
    }

    public function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);

        $basePath = __DIR__ . '/../Views';
        $viewPath = $basePath . '/' . ltrim($view, '/') . '.php';
        $layoutPath = $basePath . '/layouts/main.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException('View not found: ' . $viewPath);
        }

        $content = (static function () use ($viewPath, $data) {
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewPath;
            return ob_get_clean();
        })();

        require $layoutPath;
    }
}
