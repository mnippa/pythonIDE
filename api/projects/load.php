<?php
/**
 * Load Project API
 * Access: Owner, Admin, or anyone with share_token for public projects
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';

header('Content-Type: application/json');

$conn = getDbConnection();

// Get project ID or share token
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$shareToken = isset($_GET['token']) ? trim($_GET['token']) : null;

if (!$projectId && !$shareToken) {
    jsonResponse(['ok' => false, 'error' => 'Project ID or share token required'], 400);
}

// Load project
if ($shareToken) {
    // Access via share token (public projects only)
    $stmt = $conn->prepare('
        SELECT p.*, u.email as owner_email, u.first_name, u.last_name
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.share_token = ? AND p.visibility = ?
    ');
    $visibility = 'public';
    $stmt->bind_param('ss', $shareToken, $visibility);
} else {
    // Access via ID (requires auth)
    $user = requireAuth();
    
    $stmt = $conn->prepare('
        SELECT p.*, u.email as owner_email, u.first_name, u.last_name
        FROM projects p
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ');
    $stmt->bind_param('i', $projectId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(['ok' => false, 'error' => 'Project not found'], 404);
}

$project = $result->fetch_assoc();

// Check access rights
$isOwner = isset($user) && $user['id'] == $project['user_id'];
$isAdmin = isset($user) && $user['role'] === 'admin';
$isPublicAccess = !empty($shareToken);

if (!$isOwner && !$isAdmin && !$isPublicAccess) {
    jsonResponse(['ok' => false, 'error' => 'Access denied'], 403);
}

// Return project with access info
$ownerName = trim(($project['first_name'] ?? '') . ' ' . ($project['last_name'] ?? ''));
if ($ownerName === '') {
    $ownerName = $project['owner_email'];
}

jsonResponse([
    'ok' => true,
    'project' => [
        'id' => (int)$project['id'],
        'name' => $project['name'],
        'description' => $project['description'],
        'code' => $project['code'],
        'visibility' => $project['visibility'],
        'share_token' => $project['share_token'],
        'owner_name' => $ownerName,
        'owner_email' => $project['owner_email'],
        'created_at' => $project['created_at'],
        'updated_at' => $project['updated_at']
    ],
    'access' => [
        'can_edit' => $isOwner || $isAdmin,
        'is_owner' => $isOwner,
        'is_admin' => $isAdmin,
        'is_public' => $isPublicAccess
    ]
]);
