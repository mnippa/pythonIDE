<?php
/**
 * Add Tasks/Problems structure to database
 * Creates task table for problem organization within assignments
 * Run: php scripts/create_tasks_table.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n========================================\n";
echo "Creating Tasks/Problems Table Structure\n";
echo "========================================\n\n";

try {
    $conn = getDbConnection();
    
    echo "1. Creating tasks table...\n";
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'tasks'");
    
    if ($result->num_rows > 0) {
        echo "   ✓ Tasks table already exists\n";
    } else {
        echo "   → Creating new tasks table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS tasks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                assignment_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                position INT DEFAULT 1,
                problem_type ENUM('code_completion', 'code_fix', 'multiple_choice', 'essay') DEFAULT 'code_completion',
                code_template MEDIUMTEXT,
                hint TEXT,
                expected_output TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
                INDEX idx_assignment_id (assignment_id),
                INDEX idx_position (position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($sql)) {
            echo "   ✓ Tasks table created successfully\n";
        } else {
            throw new Exception("Failed to create tasks table: " . $conn->error);
        }
    }
    
    echo "\n2. Creating user_tasks table for progress tracking...\n";
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'user_tasks'");
    
    if ($result->num_rows > 0) {
        echo "   ✓ User tasks table already exists\n";
    } else {
        echo "   → Creating new user_tasks table...\n";
        
        $sql = "
            CREATE TABLE IF NOT EXISTS user_tasks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                task_id INT UNSIGNED NOT NULL,
                status ENUM('not_started', 'in_progress', 'completed', 'failed') DEFAULT 'not_started',
                user_code MEDIUMTEXT,
                attempts INT DEFAULT 0,
                submitted_at TIMESTAMP NULL DEFAULT NULL,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                test_results JSON,
                feedback TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_task (user_id, task_id),
                INDEX idx_user_id (user_id),
                INDEX idx_task_id (task_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        if ($conn->query($sql)) {
            echo "   ✓ User tasks table created successfully\n";
        } else {
            throw new Exception("Failed to create user_tasks table: " . $conn->error);
        }
    }
    
    echo "\n========================================\n";
    echo "✓ Tasks structure created successfully!\n";
    echo "========================================\n\n";
    
    echo "Table Structure Overview:\n\n";
    echo "tasks:\n";
    echo "  - id: Unique task identifier\n";
    echo "  - assignment_id: Link to assignment\n";
    echo "  - title: Task title\n";
    echo "  - position: Order within assignment (1, 2, 3, ...)\n";
    echo "  - problem_type: code_completion, code_fix, multiple_choice, essay\n";
    echo "  - code_template: Starter code for the task\n";
    echo "  - hint: Help text for students\n";
    echo "  - expected_output: Expected result\n\n";
    
    echo "user_tasks:\n";
    echo "  - user_id + task_id: Unique relationship\n";
    echo "  - status: not_started, in_progress, completed, failed\n";
    echo "  - user_code: Student's solution code\n";
    echo "  - attempts: Number of submission attempts\n";
    echo "  - test_results: JSON with test case results\n";
    echo "  - feedback: Auto-generated or manual feedback\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
