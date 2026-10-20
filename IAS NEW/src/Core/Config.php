<?php

namespace App\Core;

/**
 * Read-only access to config/config.php using dot notation, e.g. Config::get('security.lockout_window').
 */
final class Config
{
    private static array $items = [];

    public static function load(string $file): void
    {
        self::$items = require $file;
    }

    public static function get(string $key, $default = null)
    {
        $value = self::$items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
