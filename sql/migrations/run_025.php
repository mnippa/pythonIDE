<?php
/**
 * Migration 025: Add user_task_files table
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();

    echo "Running Migration 025: Add user_task_files...\n";

    $sql = "CREATE TABLE IF NOT EXISTS user_task_files (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        task_id INT UNSIGNED NOT NULL,
        file_path VARCHAR(1024) NOT NULL,
        content MEDIUMTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_task_file (user_id, task_id, file_path),
        INDEX idx_user_task (user_id, task_id),
        CONSTRAINT fk_user_task_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_task_files_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "✓ user_task_files table ready\n";
        echo "\n✅ Migration 025: Success!\n";
    } else {
        throw new Exception("Failed to create table: " . $conn->error);
    }

    $conn->close();

} catch (Exception $e) {
    echo "❌ Migration 025 failed: " . $e->getMessage() . "\n";
    exit(1);
}
