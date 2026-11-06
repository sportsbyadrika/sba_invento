<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $name, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::partial($name, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial('layouts/' . $layout, [...$data, 'content' => $content]);
    }

    public static function partial(string $name, array $data = []): string
    {
        $file = BASE_PATH . '/views/' . $name . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $name");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
