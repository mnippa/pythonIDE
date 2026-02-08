<?php
/**
 * User Registration API
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
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');

if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
    jsonResponse(['ok' => false, 'error' => 'Email, first name, last name and password are required'], 400);
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Invalid email format'], 400);
}

// Validate password (min 6 chars)
if (strlen($password) < 6) {
    jsonResponse(['ok' => false, 'error' => 'Password must be at least 6 characters'], 400);
}

// Validate name length
if (strlen($firstName) > 80 || strlen($lastName) > 80) {
    jsonResponse(['ok' => false, 'error' => 'First name and last name must be at most 80 characters'], 400);
}

$conn = getDbConnection();

// Check if email exists (username = email)
$stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    jsonResponse(['ok' => false, 'error' => 'Email already registered'], 409);
}

// Hash password and create user
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'user'; // Default role

$stmt = $conn->prepare('INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES (?, ?, ?, ?, ?)');
$stmt->bind_param('sssss', $email, $firstName, $lastName, $passwordHash, $role);

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    
    // Auto-login after registration
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['role'] = $role;
    
    jsonResponse([
        'ok' => true,
        'user' => [
            'id' => $userId,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Registration failed'], 500);
}
