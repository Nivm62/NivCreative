<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Plain PHP templates. Everything printed must go through e(). */
final class View
{
    public static function render(string $template, array $data = []): string
    {
        $file = Config::root() . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
