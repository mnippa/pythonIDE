<?php
/**
 * Reset database and load test data
 * Run this from command line: php scripts/load_testdata.php
 */

require_once __DIR__ . '/../config/database.php';

echo "\n========================================\n";
echo "Loading Test Data into Python IDE\n";
echo "========================================\n\n";

try {
    $conn = getDbConnection();
    
    // Check if username column exists and remove it
    echo "Checking database schema...\n";
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
    if ($result->num_rows > 0) {
        echo "  → Removing obsolete 'username' column...\n";
        
        // Drop the index first if it exists
        $conn->query("DROP INDEX idx_username ON users");
        
        // Drop the column
        $conn->query("ALTER TABLE users DROP COLUMN username");
        echo "  ✓ Username column removed\n";
    } else {
        echo "  ✓ Schema is up to date (no username column)\n";
    }
    
    // Check if first_name and last_name columns exist, add them if not
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    if ($result->num_rows == 0) {
        echo "  → Adding first_name and last_name columns...\n";
        $conn->query("ALTER TABLE users ADD COLUMN first_name VARCHAR(80) NOT NULL DEFAULT '' AFTER email");
        $conn->query("ALTER TABLE users ADD COLUMN last_name VARCHAR(80) NOT NULL DEFAULT '' AFTER first_name");
        echo "  ✓ Name columns added\n";
    } else {
        echo "  ✓ Name columns exist\n";
    }
    echo "\n";
    
    // Disable foreign key checks temporarily
    echo "Disabling foreign key checks...\n";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Truncate all tables
    echo "Clearing existing data...\n";
    $conn->query("TRUNCATE TABLE user_assignments");
    $conn->query("TRUNCATE TABLE test_cases");
    $conn->query("TRUNCATE TABLE assignments");
    $conn->query("TRUNCATE TABLE projects");
    $conn->query("TRUNCATE TABLE users");
    echo "✓ All tables cleared\n\n";
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Insert test users
    echo "Inserting test users...\n";
    
    // Admin user (email: admin@pythonide.local, password: admin123)
    $stmt = $conn->prepare("INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $email = 'admin@pythonide.local';
    $firstName = 'Sarah';
    $lastName = 'Schmidt';
    $passwordHash = '$2y$10$0BDRET8OScPxeaK7xPvP1.dp7tcvVWWCaLLfWh7UIP.WyWauGx4L6';
    $role = 'admin';
    $stmt->bind_param('sssss', $email, $firstName, $lastName, $passwordHash, $role);
    $stmt->execute();
    echo "  ✓ Admin: Sarah Schmidt (admin@pythonide.local)\n";
    
    // Regular test users (all passwords: test123)
    $testUsers = [
        ['max.mueller@example.com', 'Max', 'Müller'],
        ['anna.schulz@example.com', 'Anna', 'Schulz'],
        ['tom.weber@example.com', 'Tom', 'Weber'],
        ['lisa.fischer@example.com', 'Lisa', 'Fischer']
    ];
    
    $passwordHash = '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6';
    $role = 'user';
    
    foreach ($testUsers as $user) {
        $email = $user[0];
        $firstName = $user[1];
        $lastName = $user[2];
        $stmt->bind_param('sssss', $email, $firstName, $lastName, $passwordHash, $role);
        $stmt->execute();
        echo "  ✓ User: $firstName $lastName ($email)\n";
    }
    
    echo "\n";
    
    // Insert sample projects
    echo "Inserting sample projects...\n";
    
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
        echo "  ✓ Project: $name (User ID: $userId)\n";
    }
    
    echo "\n";
    
    // Show statistics
    $userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $projectCount = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'];
    
    echo "========================================\n";
    echo "✓ Test data loaded successfully!\n";
    echo "========================================\n\n";
    echo "Statistics:\n";
    echo "  Users: $userCount\n";
    echo "  Projects: $projectCount\n\n";
    
    echo "Test Accounts:\n";
    echo "  Admin:  admin@pythonide.local / admin123\n";
    echo "  Users:  All test users / test123\n";
    echo "          (max.mueller@example.com, anna.schulz@example.com, etc.)\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
