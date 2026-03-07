<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$columnExists = false;
$indexExists = false;

$columnResult = $conn->query("SHOW COLUMNS FROM users LIKE 'last_opened_project_id'");
if ($columnResult && $columnResult->num_rows > 0) {
    $columnExists = true;
}

if (!$columnExists) {
    $conn->query("ALTER TABLE users ADD COLUMN last_opened_project_id INT UNSIGNED NULL DEFAULT NULL AFTER role");
}

$indexResult = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'idx_last_opened_project_id'");
if ($indexResult && $indexResult->num_rows > 0) {
    $indexExists = true;
}

if (!$indexExists) {
    $conn->query("CREATE INDEX idx_last_opened_project_id ON users(last_opened_project_id)");
}

echo "Migration complete\n";
