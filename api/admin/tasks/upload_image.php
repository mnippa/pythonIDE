<?php
/**
 * Upload image for task questions or options
 * POST /api/admin/tasks/upload_image.php
 * 
 * Accepts: multipart/form-data with 'image' file
 * Returns: { ok: true, image_url: "/storage/task_images/abc123.jpg" }
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

// Validate file upload
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No valid image uploaded']);
    exit;
}

$file = $_FILES['image'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
    exit;
}

// Validate file size (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File too large. Max 5MB']);
    exit;
}

// Generate unique filename
$extension = match($mimeType) {
    'image/jpeg', 'image/jpg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    default => 'jpg'
};

$filename = uniqid('task_', true) . '_' . time() . '.' . $extension;
$uploadDir = __DIR__ . '/../../../storage/task_images/';
$uploadPath = $uploadDir . $filename;

// Ensure directory exists and is writable
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

// Return relative URL
$imageUrl = '/pythonIDE/storage/task_images/' . $filename;

echo json_encode([
    'ok' => true,
    'image_url' => $imageUrl,
    'filename' => $filename,
    'size' => $file['size']
]);
