<?php
/**
 * Session configuration helpers.
 *
 * Kept separate from config/database.php because that file is server-specific
 * and excluded from delta deployments.
 */

if (!function_exists('configureApplicationSession')) {
    function configureApplicationSession(int $ttlSeconds = 7200): void {
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
