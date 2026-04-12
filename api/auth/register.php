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
$inviteToken = trim($input['invite_token'] ?? '');

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

// Team assignment:
// 1) If invite token is provided, assign user to that active team
// 2) Fallback to date-based default mapping
$teamId = 1;

if ($inviteToken !== '') {
    $columnCheck = $conn->query("SHOW COLUMNS FROM teams LIKE 'invite_token'");
    $hasInviteToken = $columnCheck && $columnCheck->num_rows > 0;

    if ($hasInviteToken) {
        $inviteStmt = $conn->prepare('SELECT id FROM teams WHERE invite_token = ? AND is_active = 1 LIMIT 1');
        $inviteStmt->bind_param('s', $inviteToken);
        $inviteStmt->execute();
        $inviteResult = $inviteStmt->get_result();
        if ($inviteRow = $inviteResult->fetch_assoc()) {
            $teamId = (int)$inviteRow['id'];
        } else {
            jsonResponse(['ok' => false, 'error' => 'Ungültiger oder inaktiver Einladungslink'], 400);
        }
    }
} else {
    // Auto-assign team based on current date
    // WiSe: 01.10 - 28.02 → WiSe 25/26 (id=1)
    // SoSe: 01.03 - 30.09 → SoSe 26/27/28 based on year
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');

    if ($currentMonth >= 3 && $currentMonth <= 9) {
        switch ($currentYear) {
            case 2026:
                $teamId = 2;
                break;
            case 2027:
                $teamId = 3;
                break;
            case 2028:
                $teamId = 4;
                break;
            default:
                $teamId = 1;
        }
    } else {
        $teamId = 1;
    }
}

$stmt = $conn->prepare('INSERT INTO users (email, first_name, last_name, password_hash, role, team_id) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssssi', $email, $firstName, $lastName, $passwordHash, $role, $teamId);

if ($stmt->execute()) {
    $userId = $conn->insert_id;

    // New team users should immediately receive all active team default assignments.
    materializeTeamAssignmentsForUser($conn, (int)$userId, (int)$teamId, (int)$userId);
    
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
            'role' => $role,
            'team_id' => $teamId
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Registration failed'], 500);
}
