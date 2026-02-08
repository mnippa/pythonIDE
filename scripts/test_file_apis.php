#!/usr/bin/env php
<?php
/**
 * Test Script für File Management APIs
 * Usage: php scripts/test_file_apis.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/auth/middleware.php';

$conn = getDbConnection();

echo "================================\n";
echo "File Management API Test\n";
echo "================================\n\n";

// 1. Check if tables exist
echo "1. Checking database tables...\n";
$tables = ['folders', 'files', 'assignment_files'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table'");
    $row = $result->fetch_assoc();
    if ($row['count'] > 0) {
        echo "   ✓ Table '$table' exists\n";
    } else {
        echo "   ✗ Table '$table' NOT FOUND\n";
    }
}

// 2. Check table structure
echo "\n2. Checking table structure...\n";

echo "\n   folders table:\n";
$result = $conn->query("DESCRIBE folders");
while ($row = $result->fetch_assoc()) {
    echo "     - {$row['Field']} ({$row['Type']})\n";
}

echo "\n   files table:\n";
$result = $conn->query("DESCRIBE files");
while ($row = $result->fetch_assoc()) {
    echo "     - {$row['Field']} ({$row['Type']})\n";
}

echo "\n   assignment_files table:\n";
$result = $conn->query("DESCRIBE assignment_files");
while ($row = $result->fetch_assoc()) {
    echo "     - {$row['Field']} ({$row['Type']})\n";
}

// 3. Test data integrity
echo "\n3. Checking data integrity...\n";

// Check folders
$result = $conn->query("SELECT COUNT(*) as count FROM folders");
$folders_count = $result->fetch_assoc()['count'];
echo "   • Folders in database: $folders_count\n";

// Check files
$result = $conn->query("SELECT COUNT(*) as count FROM files");
$files_count = $result->fetch_assoc()['count'];
echo "   • Files in database: $files_count\n";

// Check assignment_files
$result = $conn->query("SELECT COUNT(*) as count FROM assignment_files");
$assignment_files_count = $result->fetch_assoc()['count'];
echo "   • Assignment files in database: $assignment_files_count\n";

// 4. Sample folder structure
echo "\n4. Sample folder structure from projects:\n";
$result = $conn->query("
    SELECT f.id, f.name, f.path, f.parent_folder_id, p.name as project_name
    FROM folders f 
    JOIN projects p ON f.project_id = p.id
    LIMIT 5
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   • Project: {$row['project_name']}, Folder: {$row['name']}, Path: {$row['path']}\n";
    }
} else {
    echo "   No folders created yet\n";
}

// 5. Sample files
echo "\n5. Sample files in projects:\n";
$result = $conn->query("
    SELECT f.id, f.name, f.file_type, f.file_size, p.name as project_name
    FROM files f 
    JOIN projects p ON f.project_id = p.id
    LIMIT 5
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "   • Project: {$row['project_name']}, File: {$row['name']}, Type: {$row['file_type']}, Size: {$row['file_size']} bytes\n";
    }
} else {
    echo "   No files created yet\n";
}

// 6. Validate foreign keys
echo "\n6. Validating foreign key relationships...\n";

// Check orphaned files
$result = $conn->query("
    SELECT COUNT(*) as count FROM files f 
    WHERE f.project_id NOT IN (SELECT id FROM projects)
");
$orphaned = $result->fetch_assoc()['count'];
echo "   • Orphaned files (invalid project_id): $orphaned\n";

// Check orphaned folders
$result = $conn->query("
    SELECT COUNT(*) as count FROM folders f 
    WHERE f.project_id NOT IN (SELECT id FROM projects)
");
$orphaned = $result->fetch_assoc()['count'];
echo "   • Orphaned folders (invalid project_id): $orphaned\n";

// Check orphaned assignment_files
$result = $conn->query("
    SELECT COUNT(*) as count FROM assignment_files af 
    WHERE af.assignment_id NOT IN (SELECT id FROM assignments)
");
$orphaned = $result->fetch_assoc()['count'];
echo "   • Orphaned assignment files (invalid assignment_id): $orphaned\n";

// 7. API Availability
echo "\n7. Checking API endpoints...\n";
$api_files = [
    'api/projects/folders.php' => 'Folder Management',
    'api/projects/files.php' => 'File Management',
    'api/assignments/files.php' => 'Assignment Files'
];

foreach ($api_files as $path => $name) {
    if (file_exists(__DIR__ . '/../' . $path)) {
        echo "   ✓ {$name}: {$path}\n";
    } else {
        echo "   ✗ {$name}: {$path} NOT FOUND\n";
    }
}

// 8. Middleware check
echo "\n8. Checking middleware functions...\n";
$functions = ['requireAuth', 'requireAdmin', 'getCurrentUser', 'jsonResponse'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "   ✓ Function: {$func}()\n";
    } else {
        echo "   ✗ Function: {$func}() NOT FOUND\n";
    }
}

echo "\n================================\n";
echo "Test Complete\n";
echo "================================\n";

$conn->close();
