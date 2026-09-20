<?php

declare(strict_types=1);

namespace Barama\Core;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(
        string $view,
        array $data = [],
        ?string $layout = 'layouts/app'
    ): string {
        $content = self::capture($view, $data);

        if ($layout === null) {
            return $content;
        }

        return self::capture($layout, array_merge($data, [
            'content' => $content,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $view, array $data): string
    {
        if (
            $view === ''
            || str_contains($view, '..')
            || preg_match('/^[A-Za-z0-9_\/.-]+$/', $view) !== 1
        ) {
            throw new RuntimeException('Invalid view name.');
        }

        $file = APP_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View [{$view}] was not found.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
