<?php
/**
 * Auth: reset password via token link
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$conn = getDbConnection();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$token = trim((string)($input['token'] ?? ''));
$newPassword = (string)($input['new_password'] ?? '');

if ($token === '' || $newPassword === '') {
    jsonResponse(['ok' => false, 'error' => 'Token and new password are required'], 400);
}

if (strlen($newPassword) < 6) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 6 characters'], 400);
}

$colHash = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_token_hash'");
$colExp = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_expires_at'");
$hasResetCols = ($colHash && $colHash->num_rows > 0) && ($colExp && $colExp->num_rows > 0);

if (!$hasResetCols) {
    jsonResponse(['ok' => false, 'error' => 'Reset links are not configured'], 400);
}

$tokenHash = hash('sha256', $token);
$stmt = $conn->prepare('SELECT id FROM users WHERE password_reset_token_hash = ? AND password_reset_expires_at IS NOT NULL AND password_reset_expires_at > NOW() LIMIT 1');
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    jsonResponse(['ok' => false, 'error' => 'Reset link is invalid or expired'], 400);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $conn->prepare('UPDATE users SET password_hash = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
$uid = (int)$user['id'];
$update->bind_param('si', $newHash, $uid);

if (!$update->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to reset password'], 500);
}

jsonResponse(['ok' => true, 'message' => 'Password reset successful']);
