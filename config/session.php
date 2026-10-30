<?php
/**
 * Session configuration helpers.
 *
 * Kept separate from config/database.php because that file is server-specific
 * and excluded from delta deployments.
 */

const APPLICATION_SESSION_TTL_SECONDS = 7200;

if (!function_exists('configureApplicationSession')) {
    function configureApplicationSession(int $ttlSeconds = APPLICATION_SESSION_TTL_SECONDS): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.gc_maxlifetime', (string) $ttlSeconds);
        ini_set('session.cookie_lifetime', (string) $ttlSeconds);

        session_set_cookie_params([
            'lifetime' => $ttlSeconds,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function renewApplicationSessionActivity(): void {
    $ttlSeconds = APPLICATION_SESSION_TTL_SECONDS;
    $now = time();
    if (isset($_SESSION['user_id'])) {
        $_SESSION['_application_last_activity'] = $now;
        if ((bool) ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            if (!setcookie(session_name(), session_id(), [
                'expires' => $now + $ttlSeconds,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ])) {
                throw new RuntimeException('Could not renew application session cookie');
            }
        }
    }
}

function startApplicationSession(): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $ttlSeconds = APPLICATION_SESSION_TTL_SECONDS;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        configureApplicationSession($ttlSeconds);
        if (!session_start()) {
            throw new RuntimeException('Could not start application session');
        }
    }

    $lastActivity = $_SESSION['_application_last_activity'] ?? null;
    if (isset($_SESSION['user_id']) && is_int($lastActivity) && time() - $lastActivity >= $ttlSeconds) {
        $_SESSION = [];
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Could not renew expired application session');
        }
    }

    renewApplicationSessionActivity();
    $initialized = true;
}
