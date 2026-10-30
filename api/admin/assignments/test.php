<?php
/**
 * Test endpoint to check if import API is accessible
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/session.php';

// Start session
startApplicationSession();

$response = [
    'ok' => true,
    'message' => 'Test endpoint working',
    'session_user_id' => $_SESSION['user_id'] ?? null,
    'session_role' => $_SESSION['role'] ?? null,
    'is_admin' => (($_SESSION['role'] ?? 'user') === 'admin'),
    'php_version' => phpversion(),
    'post_data_received' => file_get_contents('php://input')
];

echo json_encode($response, JSON_PRETTY_PRINT);
