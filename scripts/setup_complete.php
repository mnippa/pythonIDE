<?php
/**
 * Complete Database Setup & Update
 * Runs all database migrations and updates in the correct order
 * Run: php scripts/setup_complete.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/semester.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          Python IDE - Complete Database Setup              ║\n";
echo "║                    Version: 2.0                            ║\n";
echo "║                  Stand: 08.02.2026                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);
$steps = 0;
$errors = 0;

try {
    $conn = getDbConnection();
    
    // ============================================
    // STEP 1: Cleanup & Preparation
    // ============================================
    echo "[1/7] Schema Cleanup & Preparation...\n";
    $steps++;
    
    // Remove old username column if exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
    if ($result->num_rows > 0) {
        echo "     → Removing obsolete username column...\n";
        $conn->query("DROP INDEX idx_username ON users");
        $conn->query("ALTER TABLE users DROP COLUMN username");
    }
    
    // Add name columns if missing
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    if ($result->num_rows == 0) {
        echo "     → Adding first_name and last_name columns...\n";
        $conn->query("ALTER TABLE users ADD COLUMN first_name VARCHAR(80) NOT NULL DEFAULT '' AFTER email");
        $conn->query("ALTER TABLE users ADD COLUMN last_name VARCHAR(80) NOT NULL DEFAULT '' AFTER first_name");
    }
    
    echo "     ✓ Schema prepared\n\n";
    
    // ============================================
    // STEP 2: Add User Fields
    // ============================================
    echo "[2/7] Adding User Management Fields...\n";
    $steps++;
    
    // Add registration_date if missing
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'registration_date'");
    if ($result->num_rows == 0) {
        echo "     → Adding registration_date column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER email");
    }
    
    // Add status if missing
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($result->num_rows == 0) {
        echo "     → Adding status column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN status ENUM('aktiv', 'archiviert') NOT NULL DEFAULT 'aktiv' AFTER role");
    }
    
    echo "     ✓ User fields added\n\n";
    
    // ============================================
    // STEP 3: Update Assignments
    // ============================================
    echo "[3/7] Updating Assignments Table...\n";
    $steps++;
    
    // Add order_num if missing
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'order_num'");
    if ($result->num_rows == 0) {
        echo "     → Adding order_num column...\n";
        $conn->query("ALTER TABLE assignments ADD COLUMN order_num INT DEFAULT 0 AFTER is_active");
    }
    
    // Add position if missing
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'position'");
    if ($result->num_rows == 0) {
        echo "     → Adding position column...\n";
        $conn->query("ALTER TABLE assignments ADD COLUMN position INT DEFAULT 0 AFTER order_num");
    }
    
    echo "     ✓ Assignments updated\n\n";
    
    // ============================================
    // STEP 4: Create Tasks Tables
    // ============================================
    echo "[4/7] Creating Tasks Structure...\n";
    $steps++;
    
    // Create tasks table if missing
    $result = $conn->query("SHOW TABLES LIKE 'tasks'");
    if ($result->num_rows == 0) {
        echo "     → Creating tasks table...\n";
        
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
        
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create tasks table: " . $conn->error);
        }
    } else {
        echo "     ✓ Tasks table exists\n";
    }
    
    // Create user_tasks table if missing
    $result = $conn->query("SHOW TABLES LIKE 'user_tasks'");
    if ($result->num_rows == 0) {
        echo "     → Creating user_tasks table...\n";
        
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
        
        if (!$conn->query($sql)) {
            throw new Exception("Failed to create user_tasks table: " . $conn->error);
        }
    } else {
        echo "     ✓ User tasks table exists\n";
    }
    
    echo "     ✓ Tasks structure complete\n\n";
    
    // ============================================
    // STEP 5: Clear Old Data
    // ============================================
    echo "[5/7] Clearing Existing Data...\n";
    $steps++;
    
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("TRUNCATE TABLE user_assignments");
    $conn->query("TRUNCATE TABLE test_cases");
    $conn->query("TRUNCATE TABLE assignments");
    $conn->query("TRUNCATE TABLE user_tasks");
    $conn->query("TRUNCATE TABLE tasks");
    $conn->query("TRUNCATE TABLE projects");
    $conn->query("TRUNCATE TABLE users");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "     ✓ All data cleared\n\n";
    
    // ============================================
    // STEP 6: Load Test Users
    // ============================================
    echo "[6/7] Loading Test Users...\n";
    $steps++;
    
    $testUsers = [
        ['admin@pythonide.local', 'Sarah', 'Schmidt', '$2y$10$0BDRET8OScPxeaK7xPvP1.dp7tcvVWWCaLLfWh7UIP.WyWauGx4L6', 'admin'],
        ['max.mueller@example.com', 'Max', 'Müller', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'],
        ['anna.schulz@example.com', 'Anna', 'Schulz', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'],
        ['tom.weber@example.com', 'Tom', 'Weber', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'],
        ['lisa.fischer@example.com', 'Lisa', 'Fischer', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user']
    ];
    
    $stmt = $conn->prepare("INSERT INTO users (email, first_name, last_name, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($testUsers as $user) {
        $email = $user[0];
        $firstName = $user[1];
        $lastName = $user[2];
        $hash = $user[3];
        $role = $user[4];
        $status = 'aktiv';
        $semester = calculateSemester(new DateTime());
        
        $stmt->bind_param('ssssss', $email, $firstName, $lastName, $hash, $role, $status);
        $stmt->execute();
        
        echo "     ✓ {$firstName} {$lastName} ({$email}) [{$status}] [{$semester}]\n";
    }
    
    echo "\n";
    
    // ============================================
    // STEP 7: Load Sample Projects
    // ============================================
    echo "[7/7] Loading Sample Projects...\n";
    $steps++;
    
    $projects = [
        [2, 'Hallo Welt', 'Mein erstes Python-Programm', "print(\"Hallo Welt!\")\nprint(\"Willkommen bei Python IDE\")"],
        [2, 'Fibonacci Folge', 'Berechnet Fibonacci-Zahlen', "def fibonacci(n):\n    if n <= 1:\n        return n\n    return fibonacci(n-1) + fibonacci(n-2)\n\nfor i in range(10):\n    print(f\"F({i}) = {fibonacci(i)}\")"],
        [3, 'Liste sortieren', 'Sortiert eine Liste von Zahlen', "numbers = [64, 34, 25, 12, 22, 11, 90]\nprint(\"Unsortiert:\", numbers)\nnumbers.sort()\nprint(\"Sortiert:\", numbers)"],
        [3, 'Primzahlen', 'Findet alle Primzahlen bis 100', "def ist_primzahl(n):\n    if n < 2:\n        return False\n    for i in range(2, int(n**0.5) + 1):\n        if n % i == 0:\n            return False\n    return True\n\nprimzahlen = [n for n in range(2, 101) if ist_primzahl(n)]\nprint(\"Primzahlen bis 100:\", primzahlen)"],
        [4, 'Temperatur Umrechner', 'Celsius zu Fahrenheit', "def celsius_zu_fahrenheit(celsius):\n    return (celsius * 9/5) + 32\n\ncelsius = 25\nfahrenheit = celsius_zu_fahrenheit(celsius)\nprint(f\"{celsius}°C = {fahrenheit}°F\")"]
    ];
    
    $stmt = $conn->prepare("INSERT INTO projects (user_id, name, description, code) VALUES (?, ?, ?, ?)");
    
    foreach ($projects as $project) {
        $userId = $project[0];
        $name = $project[1];
        $description = $project[2];
        $code = $project[3];
        
        $stmt->bind_param('isss', $userId, $name, $description, $code);
        $stmt->execute();
        
        echo "     ✓ {$name}\n";
    }
    
    echo "\n";
    
    // ============================================
    // Summary
    // ============================================
    $duration = microtime(true) - $startTime;
    
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║                   ✅ SETUP COMPLETE                         ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Steps completed: $steps/7\n";
    echo "Errors: $errors\n";
    echo "Duration: " . round($duration, 2) . "s\n\n";
    
    // Database statistics
    $userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $projectCount = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
    $taskCount = $conn->query("SELECT COUNT(*) as count FROM tasks")->fetch_assoc()['count'];
    
    echo "📊 Database Statistics:\n";
    echo "   Users: $userCount\n";
    echo "   Projects: $projectCount\n";
    echo "   Tasks: $taskCount\n\n";
    
    echo "🔐 Test Accounts:\n";
    echo "   Admin:  admin@pythonide.local / admin123\n";
    echo "   Users:  All test users / test123\n\n";
    
    echo "🗓️  Current Semester: " . getCurrentSemester() . "\n\n";
    
    echo "📚 Documentation: docs/UPDATE_FINAL_SUMMARY.md\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
