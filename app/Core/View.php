<?php

declare(strict_types=1);

namespace App\Core;

/** Plain-PHP templates. Every dynamic value must go through e(). */
final class View
{
    public function __construct(private string $viewsPath) {}

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, ['content' => $content] + $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_-]+(/[a-z0-9_-]+)*$#', $template)) {
            throw new \InvalidArgumentException('Invalid template name: ' . $template);
        }
        $file = $this->viewsPath . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . $template);
        }
        $render = static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            include $__file;
        };
        ob_start();
        try {
            $render($file, $data);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
