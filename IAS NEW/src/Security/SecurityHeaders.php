<?php

namespace App\Security;

/**
 * HTTP response headers that tell the browser to enforce extra protections.
 */
final class SecurityHeaders
{
    public const CONTENT_SECURITY_POLICY =
        "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; "
        . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');
        // Only scripts and styles from this site may run; injected inline code is blocked.
        header('Content-Security-Policy: ' . self::CONTENT_SECURITY_POLICY);
        header('X-Frame-Options: DENY');                            // clickjacking
        header('X-Content-Type-Options: nosniff');                  // MIME sniffing
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        // Pages with personal data must never be stored by the browser or a proxy.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
