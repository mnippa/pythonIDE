<?php
/**
 * Update Project API
 * Access: Owner or Admin only
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

$projectId = isset($input['id']) ? (int)$input['id'] : null;

if (!$projectId) {
    jsonResponse(['ok' => false, 'error' => 'Project ID required'], 400);
}

// Load project to check ownership
$stmt = $conn->prepare('SELECT user_id, visibility FROM projects WHERE id = ?');
$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Project not found'], 404);
}

$project = $result->fetch_assoc();

// Check access (owner or admin)
$isOwner = $user['id'] == $project['user_id'];
$isAdmin = $user['role'] === 'admin';

if (!$isOwner && !$isAdmin) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

// Validate input
$name = isset($input['name']) ? trim($input['name']) : null;
$description = isset($input['description']) ? trim($input['description']) : null;
$code = isset($input['code']) ? $input['code'] : null;
$visibility = isset($input['visibility']) ? $input['visibility'] : null;

// Check code size if provided
if ($code !== null) {
    $stmt = $conn->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $key = 'project_code_max_size';
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $maxSize = $result->num_rows > 0 ? (int)$result->fetch_assoc()['setting_value'] : 102400;
    
    if (strlen($code) > $maxSize) {
        jsonResponse(['ok' => false, 'error' => "Code exceeds maximum size of " . ($maxSize/1024) . "KB"], 400);
    }
}

// Validate visibility
if ($visibility !== null && !in_array($visibility, ['private', 'public'])) {
    jsonResponse(['ok' => false, 'error' => 'Invalid visibility'], 400);
}

// Build update query dynamically
$updates = [];
$params = [];
$types = '';

if ($name !== null) {
    $updates[] = 'name = ?';
    $params[] = $name;
    $types .= 's';
}
if ($description !== null) {
    $updates[] = 'description = ?';
    $params[] = $description;
    $types .= 's';
}
if ($code !== null) {
    $updates[] = 'code = ?';
    $params[] = $code;
    $types .= 's';
}
if ($visibility !== null) {
    $updates[] = 'visibility = ?';
    $params[] = $visibility;
    $types .= 's';
    
    // Generate/remove share token based on visibility
    if ($visibility === 'public' && empty($project['share_token'])) {
        $shareToken = bin2hex(random_bytes(16));
        $updates[] = 'share_token = ?';
        $params[] = $shareToken;
        $types .= 's';
    } elseif ($visibility === 'private') {
        $updates[] = 'share_token = NULL';
    }
}

if (empty($updates)) {
    jsonResponse(['ok' => false, 'error' => 'No fields to update'], 400);
}

// Add project ID to params
$params[] = $projectId;
$types .= 'i';

$sql = 'UPDATE projects SET ' . implode(', ', $updates) . ' WHERE id = ?';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Reload project
    $stmt = $conn->prepare('SELECT id, name, description, visibility, share_token, updated_at FROM projects WHERE id = ?');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $updated = $result->fetch_assoc();
    
    jsonResponse([
        'ok' => true,
        'project' => [
            'id' => (int)$updated['id'],
            'name' => $updated['name'],
            'description' => $updated['description'],
            'visibility' => $updated['visibility'],
            'share_token' => $updated['share_token'],
            'updated_at' => $updated['updated_at']
        ]
    ]);
} else {
    jsonResponse(['ok' => false, 'error' => 'Failed to update project'], 500);
}
