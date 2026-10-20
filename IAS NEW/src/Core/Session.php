<?php

namespace App\Core;

use App\Security\AuditLog;

/**
 * Hardened PHP session: strict cookies, idle and absolute timeouts,
 * browser binding and periodic ID rotation.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = Config::get('session');

        ini_set('session.use_strict_mode', '1');   // reject session IDs the server did not create
        ini_set('session.use_only_cookies', '1');  // never accept IDs from the URL
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $cfg['absolute_timeout']);

        session_name($cfg['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,      // JavaScript cannot read the cookie
            'samesite' => 'Strict',  // the cookie is never sent on cross-site requests
        ]);
        session_start();

        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $now = time();

        if ($now - ($_SESSION['last_activity'] ?? 0) > $cfg['idle_timeout']) {
            self::destroy('Your session expired due to inactivity. Please sign in again.', 'session_idle_timeout');
            return;
        }
        if ($now - ($_SESSION['login_time'] ?? 0) > $cfg['absolute_timeout']) {
            self::destroy('Your session has reached its maximum length. Please sign in again.', 'session_absolute_timeout');
            return;
        }

        // A stolen cookie replayed from a different browser is rejected.
        if (!hash_equals($_SESSION['fingerprint'] ?? '', self::fingerprint())) {
            self::destroy('Your session was ended for security reasons. Please sign in again.', 'session_hijack_blocked');
            return;
        }

        if ($now - ($_SESSION['regenerated_at'] ?? 0) > $cfg['regenerate_every']) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = $now;
        }

        $_SESSION['last_activity'] = $now;
    }

    public static function fingerprint(): string
    {
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    }

    /**
     * Replaces the session contents after a privilege change (prevents session fixation).
     */
    public static function renew(array $data): void
    {
        session_regenerate_id(true);
        $_SESSION = $data;
    }

    /**
     * Ends the session completely, then opens a fresh empty one so a message can still be shown.
     */
    public static function destroy(?string $message = null, ?string $auditEvent = null): void
    {
        if ($auditEvent !== null) {
            AuditLog::record($auditEvent);
        }

        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Strict',
        ]);
        session_destroy();

        session_start();
        session_regenerate_id(true);
        if ($message !== null) {
            self::flash('info', $message);
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlashes(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}
