<?php
/**
 * Admin: Update user data (status, role, team)
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAdmin();
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

$idFromBody = isset($input['id']) ? (int)$input['id'] : 0;
$idFromQuery = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $idFromBody > 0 ? $idFromBody : $idFromQuery;

if ($userId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'User ID required'], 400);
}

$stmt = $conn->prepare('SELECT id, role, email, first_name, last_name FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
if (!$existing) {
    jsonResponse(['ok' => false, 'error' => 'User not found'], 404);
}

$updates = [];
$types = '';
$params = [];

if (array_key_exists('status', $input)) {
    $status = (string)$input['status'];
    $allowedStatus = ['aktiv', 'archiviert'];
    if (!in_array($status, $allowedStatus, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid status'], 400);
    }
    $updates[] = 'status = ?';
    $types .= 's';
    $params[] = $status;
}

if (array_key_exists('email', $input)) {
    $email = trim((string)$input['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid email'], 400);
    }

    $checkStmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    $checkStmt->bind_param('si', $email, $userId);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        jsonResponse(['ok' => false, 'error' => 'Email already in use'], 400);
    }

    $updates[] = 'email = ?';
    $types .= 's';
    $params[] = $email;
}

if (array_key_exists('first_name', $input)) {
    $firstName = trim((string)$input['first_name']);
    $updates[] = 'first_name = ?';
    $types .= 's';
    $params[] = $firstName;
}

if (array_key_exists('last_name', $input)) {
    $lastName = trim((string)$input['last_name']);
    $updates[] = 'last_name = ?';
    $types .= 's';
    $params[] = $lastName;
}

if (array_key_exists('role', $input)) {
    $role = (string)$input['role'];
    $allowedRoles = ['user', 'admin'];
    if (!in_array($role, $allowedRoles, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid role'], 400);
    }

    // Never allow removing the role from the currently logged-in admin.
    if ((int)$user['id'] === $userId && $role !== 'admin') {
        jsonResponse(['ok' => false, 'error' => 'You cannot remove your own admin role'], 400);
    }

    if ($existing['role'] === 'admin' && $role !== 'admin') {
        $adminCountResult = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
        $adminCount = (int)(($adminCountResult && $adminCountResult->num_rows > 0)
            ? ($adminCountResult->fetch_assoc()['c'] ?? 0)
            : 0);
        if ($adminCount <= 1) {
            jsonResponse(['ok' => false, 'error' => 'Last admin protection: at least one admin must remain'], 400);
        }
    }

    $updates[] = 'role = ?';
    $types .= 's';
    $params[] = $role;
}

if (array_key_exists('team_id', $input)) {
    $teamId = $input['team_id'];
    if ($teamId === '' || $teamId === null) {
        $updates[] = 'team_id = NULL';
    } else {
        $teamId = (int)$teamId;
        if ($teamId <= 0) {
            jsonResponse(['ok' => false, 'error' => 'Invalid team_id'], 400);
        }

        $teamStmt = $conn->prepare('SELECT id FROM teams WHERE id = ?');
        $teamStmt->bind_param('i', $teamId);
        $teamStmt->execute();
        if ($teamStmt->get_result()->num_rows === 0) {
            jsonResponse(['ok' => false, 'error' => 'Team not found'], 400);
        }

        $updates[] = 'team_id = ?';
        $types .= 'i';
        $params[] = $teamId;
    }
}

if (count($updates) === 0) {
    jsonResponse(['ok' => false, 'error' => 'No valid fields provided'], 400);
}

$sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
$types .= 'i';
$params[] = $userId;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if (!$stmt->execute()) {
    jsonResponse(['ok' => false, 'error' => 'Failed to update user'], 500);
}

$resultStmt = $conn->prepare('SELECT id, email, first_name, last_name, role, status, team_id FROM users WHERE id = ?');
$resultStmt->bind_param('i', $userId);
$resultStmt->execute();
$updated = $resultStmt->get_result()->fetch_assoc();

jsonResponse([
    'ok' => true,
    'message' => 'User updated',
    'user' => [
        'id' => (int)$updated['id'],
        'email' => $updated['email'],
        'first_name' => $updated['first_name'],
        'last_name' => $updated['last_name'],
        'role' => $updated['role'],
        'status' => $updated['status'],
        'team_id' => $updated['team_id'] !== null ? (int)$updated['team_id'] : null,
    ],
]);
