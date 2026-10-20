<?php
/**
 * Small global helpers used by page controllers and view templates.
 * They delegate to the classes in src/ and keep templates readable.
 */

use App\Auth\Auth;
use App\Auth\Rbac;
use App\Core\Session;
use App\Core\View;
use App\Security\Csrf;

/* -------------------------------------------------------------------------
 * Request / response
 * ---------------------------------------------------------------------- */

/** Escapes text for HTML output (XSS prevention). Use for every dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

/** REMOTE_ADDR is the only client address that cannot be spoofed with a header. */
function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed text input, always valid UTF-8 (invalid byte sequences are replaced). */
function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim(mb_scrub($value, 'UTF-8')) : '';
}

/** Raw (untrimmed) string input, used for passwords. */
function post_raw(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function post_int(string $key): int
{
    return (int) (filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT) ?: 0);
}

function query_string(string $key, int $maxLength = 100): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? mb_substr(trim(mb_scrub($value, 'UTF-8')), 0, $maxLength) : '';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit();
}

/** Only known local pages are allowed as redirect targets (prevents open redirects). */
function safe_next(mixed $next, string $default): string
{
    $allowed = ['admin_dashboard.php', 'admin_tickets.php', 'manage_users.php', 'audit_log.php', 'account.php', 'dashboard.php'];
    return in_array($next, $allowed, true) ? $next : $default;
}

function view(string $template, array $data = [], string $layout = 'app', int $status = 200): void
{
    View::render($template, $data, $layout, $status);
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

function csrf_field(): string
{
    return Csrf::field();
}

function verify_csrf(): void
{
    Csrf::verify();
}

function current_user(): ?array
{
    return Auth::user();
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function home_for(array $user): string
{
    return Rbac::homeFor($user['role']);
}

/* -------------------------------------------------------------------------
 * Presentation
 * ---------------------------------------------------------------------- */

/** A list of validation errors as an alert box (empty string when there are none). */
function form_errors(array $errors, string $intro = ''): string
{
    if (!$errors) {
        return '';
    }
    $items = implode('', array_map(static fn ($err) => '<li>' . e($err) . '</li>', array_unique($errors)));
    return '<div class="alert alert-error" role="alert">' . ($intro !== '' ? e($intro) : '') . '<ul>' . $items . '</ul></div>';
}

function badge_class(string $value): string
{
    return 'badge-' . strtolower(str_replace(' ', '-', $value));
}

function format_date(?string $value): string
{
    return $value ? date('M j, Y · g:i A', strtotime($value)) : 'Never';
}

function greeting(): string
{
    $hour = (int) date('G');
    return $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
}

const ICONS = [
    'home'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
    'ticket' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
    'list'   => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
    'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'inbox'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'users'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'shield' => '<path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7l8-4z"/><path d="m9 12 2 2 4-4"/>',
    'file'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
    'lock'   => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
    'plus'   => '<path d="M12 5v14M5 12h14"/>',
    'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'check'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
    'alert'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
    'flame'  => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
    'key'    => '<circle cx="8" cy="15" r="4"/><path d="m10.85 12.15 8.65-8.65M18 5l3 3M15 8l2 2"/>',
    'menu'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
];

function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . (ICONS[$name] ?? '') . '</svg>';
}

/** A statistic tile. $tone colours the icon chip only. */
function stat_card(string $label, mixed $value, string $iconName, string $tone = 'green', string $hint = ''): string
{
    return '<div class="stat">'
        . '<div class="stat-head"><p class="stat-label">' . e($label) . '</p>'
        . '<span class="stat-icon stat-icon-' . e($tone) . '">' . icon($iconName) . '</span></div>'
        . '<p class="stat-value">' . e($value) . '</p>'
        . ($hint !== '' ? '<p class="stat-hint">' . e($hint) . '</p>' : '')
        . '</div>';
}

function brand_lockup(string $extraClass = '', string $star = 'star.svg'): string
{
    return '<a class="brand ' . e($extraClass) . '" href="index.php" aria-label="USLS IT Helpdesk home">'
        . '<img class="brand-mark" src="assets/img/' . e($star) . '" alt="">'
        . '<span class="brand-text"><span class="brand-kicker">University of St. La Salle</span>'
        . '<span class="brand-name">IT <span>Helpdesk</span></span></span></a>';
}
