<?php
/**
 * Delete task image
 * POST /api/admin/tasks/delete_image.php
 * 
 * Body: { "image_url": "/pythonIDE/storage/task_images/abc123.jpg" }
 * Returns: { ok: true }
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

// Check authentication and admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['image_url'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing image_url']);
    exit;
}

$imageUrl = $input['image_url'];

// Extract filename from URL
// Expected format: /pythonIDE/storage/task_images/filename.jpg
if (!preg_match('#/storage/task_images/([a-zA-Z0-9_\.]+)$#', $imageUrl, $matches)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid image URL format']);
    exit;
}

$filename = $matches[1];
$filePath = __DIR__ . '/../../../storage/task_images/' . $filename;

// Security check: ensure file is in task_images directory
$realPath = realpath($filePath);
$uploadDir = realpath(__DIR__ . '/../../../storage/task_images/');

if ($realPath === false || strpos($realPath, $uploadDir) !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file path']);
    exit;
}

// Delete file if it exists
if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to delete file']);
        exit;
    }
}

echo json_encode(['ok' => true, 'message' => 'Image deleted']);
