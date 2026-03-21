<?php
/**
 * Auth: change own password (logged-in user)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$currentUser = requireAuth();
$conn = getDbConnection();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    jsonResponse(['ok' => false, 'error' => 'Current and new password are required'], 400);
}

if (strlen($newPassword) < 6) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 6 characters'], 400);
}

$stmt = $conn->prepare('SELECT id, password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $currentUser['id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

if (!password_verify($currentPassword, (string)$row['password_hash'])) {
    jsonResponse(['ok' => false, 'error' => 'Current password is incorrect'], 400);
}

if (password_verify($newPassword, (string)$row['password_hash'])) {
    jsonResponse(['ok' => false, 'error' => 'New password must be different'], 400);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$hasResetHash = false;
$hasResetExpiry = false;
$colHash = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_token_hash'");
if ($colHash && $colHash->num_rows > 0) {
    $hasResetHash = true;
}
$colExp = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_expires_at'");
if ($colExp && $colExp->num_rows > 0) {
    $hasResetExpiry = true;
}

if ($hasResetHash && $hasResetExpiry) {
    $update = $conn->prepare('UPDATE users SET password_hash = ?, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
    $update->bind_param('si', $newHash, $currentUser['id']);
} else {
    $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->bind_param('si', $newHash, $currentUser['id']);
}

if (!$update->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to update password'], 500);
}

jsonResponse(['ok' => true, 'message' => 'Password updated']);
