<?php

namespace App\Auth;

use App\Core\Database;
use App\Core\Mailer;
use App\Security\AuditLog;
use App\Security\Secrets;

/**
 * One-time codes sent to the account's e-mail address.
 * Codes are stored only as keyed hashes, work once and expire after 10 minutes. Wrong guesses are
 * limited by the same two-factor lockout as authenticator codes, and sending is limited to
 * prevent e-mail flooding.
 */
final class EmailCodes
{
    public const TTL_SECONDS = 600;
    private const RESEND_GAP_SECONDS = 60;
    private const MAX_PER_HOUR = 5;

    /**
     * @return string one of: sent, too_soon, limit, failed
     */
    public static function send(array $user, string $purpose): string
    {
        $pdo = Database::connection();
        $id = (int) $user['id'];

        $pdo->prepare('DELETE FROM email_codes WHERE created_at < NOW() - INTERVAL 1 DAY')->execute();
        $stmt = $pdo->prepare(
            // The hourly limit covers every e-mail; the short wait is per purpose, so finishing
            // setup and signing straight back in still gets a sign-in code.
            'SELECT COUNT(*) AS total, MIN(IF(purpose = ?, TIMESTAMPDIFF(SECOND, created_at, NOW()), NULL)) AS newest
             FROM email_codes WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR'
        );
        $stmt->execute([$purpose, $id]);
        $recent = $stmt->fetch();
        if ((int) $recent['total'] >= self::MAX_PER_HOUR) {
            AuditLog::record('email_code_limit', 'Hourly limit reached', $id, $user['username']);
            return 'limit';
        }
        if ($recent['newest'] !== null && (int) $recent['newest'] < self::RESEND_GAP_SECONDS) {
            return 'too_soon';
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // A new code replaces any earlier one, so only the latest e-mail works.
        $pdo->prepare('UPDATE email_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$id]);
        $pdo->prepare('INSERT INTO email_codes (user_id, purpose, code_hash, expires_at) VALUES (?, ?, ?, NOW() + INTERVAL ? SECOND)')
            ->execute([$id, $purpose, self::hash($id, $purpose, $code), self::TTL_SECONDS]);
        $rowId = (int) $pdo->lastInsertId();

        $subject = $purpose === 'reset'
            ? 'Your USLS IT Helpdesk password reset code'
            : 'Your USLS IT Helpdesk code: ' . $code;
        if (!Mailer::send($user['email'], $subject, self::body($code, $purpose))) {
            // Not delivered: remove it so a mail outage does not use up the sending limit.
            $pdo->prepare('DELETE FROM email_codes WHERE id = ?')->execute([$rowId]);
            AuditLog::record('email_code_failed', 'The mail server did not accept the message', $id, $user['username']);
            return 'failed';
        }
        AuditLog::record('email_code_sent', 'Purpose: ' . $purpose, $id, $user['username']);
        return 'sent';
    }

    /** Checks a code and uses it up. A code works once, for one purpose, until it expires. */
    public static function verify(int $userId, string $purpose, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, code_hash FROM email_codes
             WHERE user_id = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId, $purpose]);
        $row = $stmt->fetch();
        if (!$row || !hash_equals($row['code_hash'], self::hash($userId, $purpose, $code))) {
            return false;
        }
        // Atomic: if two requests send the same code at once, only one succeeds.
        $use = $pdo->prepare('UPDATE email_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $use->execute([$row['id']]);
        return $use->rowCount() === 1;
    }

    /** "juan.delacruz@gmail.com" becomes "ju***@gmail.com" */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($local, 0, 2) . '***@' . $domain;
    }

    /** What to tell the user for each result of send(). */
    public static function message(string $status): string
    {
        return [
            'sent'     => 'We sent a new code to your e-mail.',
            'too_soon' => 'Please wait a minute before asking for another code.',
            'limit'    => 'Too many codes were requested. Please try again in an hour.',
            'failed'   => 'We could not send the e-mail. Please try again in a moment.',
        ][$status] ?? '';
    }

    private static function hash(int $userId, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$userId}|{$purpose}|{$code}", Secrets::get('pepper'));
    }

    private static function body(string $code, string $purpose): string
    {
        $minutes = (int) (self::TTL_SECONDS / 60);
        if ($purpose === 'reset') {
            return "Your USLS IT Helpdesk password reset code is: {$code}\n\n"
                . "It expires in {$minutes} minutes and works once. IT Services will never ask you for this code.\n\n"
                . "If you did not request a password reset, you can ignore this message. Your password has not been changed.\n";
        }
        return "Your USLS IT Helpdesk code is: {$code}\n\n"
            . "It expires in {$minutes} minutes and works once. IT Services will never ask you for this code.\n\n"
            . "If you did not just try to sign in, someone may know your password. Please change it.\n";
    }
}
