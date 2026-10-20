<?php

namespace App\Security;

/**
 * Secret keys (password pepper and encryption key) live in config/secrets.php,
 * generated on first run and excluded from Git. A leaked database or source
 * repository alone is therefore not enough to crack passwords or read 2FA secrets.
 */
final class Secrets
{
    private static ?array $secrets = null;

    public static function get(string $name): string
    {
        if (self::$secrets === null) {
            $file = APP_ROOT . '/config/secrets.php';
            if (!is_file($file)) {
                self::generate($file);
            }
            self::$secrets = require $file;
        }
        return self::$secrets[$name];
    }

    private static function generate(string $file): void
    {
        $keys = [
            'pepper'         => bin2hex(random_bytes(32)),
            'encryption_key' => bin2hex(random_bytes(32)),
        ];
        $php = "<?php\n// Auto-generated. Keep this file private and out of version control.\nreturn " . var_export($keys, true) . ";\n";
        if (file_put_contents($file, $php, LOCK_EX) === false) {
            http_response_code(500);
            exit('Unable to create config/secrets.php. Check folder permissions.');
        }
        @chmod($file, 0600);
    }
}
