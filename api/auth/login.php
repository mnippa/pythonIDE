<?php
/**
 * User Login API
 */

require_once __DIR__ . '/../../config/database.php';

session_start();
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

// Validate required fields
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    jsonResponse(['ok' => false, 'error' => 'Email and password are required'], 400);
}

$conn = getDbConnection();

// Get user by email
$stmt = $conn->prepare('SELECT id, email, first_name, last_name, password_hash, role, team_id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email or password'], 401);
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email or password'], 401);
}

// Ensure team defaults are materialized for existing users as soon as they log in.
materializeTeamAssignmentsForUser($conn, (int)$user['id'], isset($user['team_id']) ? (int)$user['team_id'] : null, (int)$user['id']);

// Update last login
$stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['first_name'] = $user['first_name'] ?? '';
$_SESSION['last_name'] = $user['last_name'] ?? '';
$_SESSION['role'] = $user['role'];

jsonResponse([
    'ok' => true,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? '',
        'role' => $user['role']
    ]
]);
