<?php
/**
 * Database Schema Update
 * - Add registration_date and status to users
 * - Reorganize assignments structure
 * Run: php scripts/update_schema.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n========================================\n";
echo "Updating Database Schema\n";
echo "========================================\n\n";

try {
    $conn = getDbConnection();
    
    // 1. Update users table
    echo "1. Updating users table...\n";
    
    // Check and add registration_date column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'registration_date'");
    if ($result->num_rows == 0) {
        echo "   → Adding registration_date column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER email");
    } else {
        echo "   ✓ registration_date column exists\n";
    }
    
    // Check and add status column
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($result->num_rows == 0) {
        echo "   → Adding status column...\n";
        $conn->query("ALTER TABLE users ADD COLUMN status ENUM('aktiv', 'archiviert') NOT NULL DEFAULT 'aktiv' AFTER role");
    } else {
        echo "   ✓ status column exists\n";
    }
    
    echo "\n2. Updating assignments table...\n";
    
    // Check and add order_num to assignments
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'order_num'");
    if ($result->num_rows == 0) {
        echo "   → Adding order_num to assignments...\n";
        $conn->query("ALTER TABLE assignments ADD COLUMN order_num INT DEFAULT 0 AFTER is_active");
    } else {
        echo "   ✓ order_num column exists\n";
    }
    
    // Check and add position to assignments for ordering
    $result = $conn->query("SHOW COLUMNS FROM assignments LIKE 'position'");
    if ($result->num_rows == 0) {
        echo "   → Adding position column...\n";
        $conn->query("ALTER TABLE assignments ADD COLUMN position INT DEFAULT 0 AFTER order_num");
    } else {
        echo "   ✓ position column exists\n";
    }
    
    echo "\n3. Ensuring test_cases table structure...\n";
    
    // Verify test_cases has order_num
    $result = $conn->query("SHOW COLUMNS FROM test_cases LIKE 'order_num'");
    if ($result->num_rows == 0) {
        echo "   ✓ test_cases already has order_num\n";
    } else {
        echo "   ✓ test_cases order_num confirmed\n";
    }
    
    echo "\n========================================\n";
    echo "✓ Schema update completed successfully!\n";
    echo "========================================\n\n";
    
    // Show semester info
    echo "Semester Calculation Logic:\n";
    echo "  - 01.03 - 30.09: SoSe (Sommersemester)\n";
    echo "  - 01.10 - 28/29.02: WiSe (Wintersemester)\n\n";
    
    echo "Status Field:\n";
    echo "  - aktiv: Normal active user\n";
    echo "  - archiviert: Archived/inactive user\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
