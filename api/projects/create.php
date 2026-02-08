<?php
/**
 * Create Project API
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$user = requireAuth();
$conn = getDbConnection();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['ok' => false, 'error' => 'Invalid JSON'], 400);
}

// Validate input
$name = trim($input['name'] ?? '');
$description = trim($input['description'] ?? '');
$code = $input['code'] ?? '';
$visibility = $input['visibility'] ?? 'private';

if (empty($name)) {
    jsonResponse(['ok' => false, 'error' => 'Project name is required'], 400);
}

if (!in_array($visibility, ['private', 'public'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid visibility'], 400);
}

// Check code size limit
$stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
$key = 'project_code_max_size';
$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$maxSize = $result->num_rows > 0 ? (int)$result->fetch_assoc()['setting_value'] : 102400;

if (strlen($code) > $maxSize) {
    jsonResponse(['ok' => false, 'error' => "Code exceeds maximum size of " . ($maxSize/1024) . "KB"], 400);
}

// Check project limit
$key = 'project_limit_per_user';
$stmt->bind_param('s', $key);
$stmt->execute();
$result = $stmt->get_result();
$maxProjects = $result->num_rows > 0 ? (int)$result->fetch_assoc()['setting_value'] : 50;

$stmt = $conn->prepare('SELECT COUNT(*) as count FROM projects WHERE user_id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$currentCount = $result->fetch_assoc()['count'];

if ($currentCount >= $maxProjects) {
    jsonResponse(['ok' => false, 'error' => "Project limit reached ($maxProjects projects)"], 403);
}

// Generate share token for public projects
$shareToken = null;
if ($visibility === 'public') {
    $shareToken = bin2hex(random_bytes(16));
}

// Create project
$stmt = $conn->prepare('INSERT INTO projects (user_id, name, description, code, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('isssss', $user['id'], $name, $description, $code, $visibility, $shareToken);

if ($stmt->execute()) {
    $projectId = $conn->insert_id;
    
    jsonResponse([
        'ok' => true,
        'project' => [
            'id' => $projectId,
            'name' => $name,
            'description' => $description,
            'visibility' => $visibility,
            'share_token' => $shareToken,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ], 201);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to create project'], 500);
}
