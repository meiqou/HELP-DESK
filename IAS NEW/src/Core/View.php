<?php

namespace App\Core;

/**
 * Renders a template from views/pages inside the shared layout.
 * Templates only display data; they never query the database or change state.
 */
final class View
{
    /**
     * @param string $template  file name in views/pages, without .php
     * @param array  $data      variables made available to the template
     * @param string $layout    'app' (sidebar when signed in), 'public' (top bar) or 'auth' (split sign-in screen)
     */
    public static function render(string $template, array $data = [], string $layout = 'app', int $status = 200): void
    {
        http_response_code($status);

        $content = self::capture(APP_ROOT . '/views/pages/' . $template . '.php', $data);
        echo self::capture(APP_ROOT . '/views/layout.php', $data + ['content' => $content, 'layout' => $layout]);
    }

    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
