<?php
/**
 * Admin: create password reset link for a user (no mail required)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$admin = requireAdmin();
$conn = getDbConnection();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$userId = (int)($input['user_id'] ?? 0);
if ($userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'user_id required'], 400);
}

$stmt = $conn->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$targetUser = $stmt->get_result()->fetch_assoc();

if (!$targetUser) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

$colHash = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_token_hash'");
if (!$colHash || $colHash->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN password_reset_token_hash VARCHAR(64) NULL");
    $conn->query("CREATE INDEX idx_users_password_reset_token_hash ON users(password_reset_token_hash)");
}

$colExp = $conn->query("SHOW COLUMNS FROM users LIKE 'password_reset_expires_at'");
if (!$colExp || $colExp->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN password_reset_expires_at DATETIME NULL");
}

$token = bin2hex(random_bytes(24));
$tokenHash = hash('sha256', $token);

$update = $conn->prepare('UPDATE users SET password_reset_token_hash = ?, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?');
$update->bind_param('si', $tokenHash, $userId);
if (!$update->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to create reset link'], 500);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$appBase = preg_replace('#/api/admin/users$#', '', $scriptDir);
if ($appBase === null || $appBase === $scriptDir) {
    $appBase = preg_replace('#/api(?:/.*)?$#', '', $scriptDir) ?? '';
}
$resetPath = rtrim($appBase, '/') . '/public/reset-password.php';
$resetLink = $scheme . '://' . $host . $resetPath . '?token=' . urlencode($token);

jsonResponse([
    'ok' => true,
    'message' => 'Reset link created',
    'user' => [
        'id' => (int)$targetUser['id'],
        'email' => $targetUser['email'],
    ],
    'reset_link' => $resetLink,
    'expires_in_hours' => 24,
    'created_by_admin_id' => (int)$admin['id'],
]);
