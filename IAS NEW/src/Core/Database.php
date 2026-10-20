<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Single shared PDO connection. Native prepared statements (no emulation)
 * mean user input is always sent separately from the SQL text.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $c = Config::get('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";

        try {
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Keep MySQL's NOW() in the same timezone as PHP's date().
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            // Never reveal host names, users or driver messages to the browser.
            error_log('Database connection failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                throw $e;
            }
            http_response_code(503);
            exit('The helpdesk is temporarily unavailable. Please try again later.');
        }

        return self::$pdo;
    }
}
