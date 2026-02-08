<?php
/**
 * Get current session info
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/middleware.php';

header('Content-Type: application/json');

session_start();

$user = getCurrentUser();

if ($user) {
    jsonResponse([
        'ok' => true,
        'logged_in' => true,
        'user' => $user
    ]);
} else {
    jsonResponse([
        'ok' => true,
        'logged_in' => false,
        'user' => null
    ]);
}
