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

// Ensure project files tables exist before using them
ensureProjectFilesTablesExist($conn);

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
    
    // Initialize default folder structure
    initializeDefaultProjectStructure($conn, $projectId, $name);
    
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

/**
 * Initialize default project structure
 * Creates: includes/, img/ folders and projectname.py file
 */
function initializeDefaultProjectStructure($conn, $projectId, $projectName)
{
    try {
        // Create includes folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'includes';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        // Create img folder
        $stmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
        $folderName = 'img';
        $stmt->bind_param('is', $projectId, $folderName);
        $stmt->execute();
        
        // Create projectname.py file (at root, folder_id = NULL)
        $safeName = preg_replace('/\s+/', '_', trim($projectName));
        $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '', $safeName);
        if ($safeName === '') {
            $safeName = 'project';
        }
        $fileName = $safeName . '.py';
        $content = "# " . $projectName . "\n\n# Start coding here!\n";
        $mimeType = 'text/plain';
        $fileSize = strlen($content);
        
        $stmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
        $stmt->bind_param('isssi', $projectId, $fileName, $content, $mimeType, $fileSize);
        $stmt->execute();
        
    } catch (Exception $e) {
        // Log error but don't fail project creation
        error_log('Failed to initialize project structure: ' . $e->getMessage());
    }
}

/**
 * Ensure required tables exist - creates them if missing (idempotent)
 */
function ensureProjectFilesTablesExist($conn) {
    try {
        // Check if project_folders table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_folders'");
        if($result->num_rows == 0) {
            // Create project_folders table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_folders (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    project_id INT UNSIGNED NOT NULL,
                    parent_folder_id INT UNSIGNED,
                    name VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    FOREIGN KEY (parent_folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                    INDEX (project_id),
                    INDEX (parent_folder_id),
                    UNIQUE KEY unique_folder_name (project_id, parent_folder_id, name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        // Check if project_files table exists
        $result = $conn->query("SHOW TABLES LIKE 'project_files'");
        if($result->num_rows == 0) {
            // Create project_files table
            $conn->query("
                CREATE TABLE IF NOT EXISTS project_files (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    project_id INT UNSIGNED NOT NULL,
                    folder_id INT UNSIGNED,
                    name VARCHAR(255) NOT NULL,
                    content MEDIUMTEXT,
                    mime_type VARCHAR(100) DEFAULT 'text/plain',
                    file_size INT UNSIGNED DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    FOREIGN KEY (folder_id) REFERENCES project_folders(id) ON DELETE CASCADE,
                    INDEX (project_id),
                    INDEX (folder_id),
                    UNIQUE KEY unique_file_name (project_id, folder_id, name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (Exception $e) {
        error_log('Failed to ensure project files tables: ' . $e->getMessage());
    }
}
