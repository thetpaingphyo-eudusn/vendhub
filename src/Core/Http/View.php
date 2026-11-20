<?php

declare(strict_types=1);

namespace App\Core\Http;

final class View
{
    public function __construct(private string $path)
    {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->path . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        $data['title'] ??= 'Vending Machine';
        $data['authUser'] ??= null;
        $data['csrf'] ??= $_SESSION['csrf'] ?? '';
        $data['flash'] ??= null;
        $data['errors'] ??= [];
        $data['old'] ??= [];

        $viewsPath = $this->path;
        $partial = static function (string $name, array $extra = []) use ($data, $viewsPath): void {
            extract(array_merge($data, $extra), EXTR_SKIP);
            include $viewsPath . '/' . $name . '.php';
        };

        $layout = (string) ($data['layout'] ?? 'layout');
        $layoutFile = $this->path . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout not found: ' . $layout);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        ob_start();
        include $layoutFile;

        return (string) ob_get_clean();
    }
}
