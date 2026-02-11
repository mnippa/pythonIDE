<?php
/**
 * Test database structure after migration
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
    
    $result = [];
    
    // Check if assignments table exists
    $query = "SHOW TABLES LIKE 'assignments'";
    $res = $conn->query($query);
    $result['assignments_table_exists'] = $res->num_rows > 0;
    
    // Check if tasks table exists
    $query = "SHOW TABLES LIKE 'tasks'";
    $res = $conn->query($query);
    $result['tasks_table_exists'] = $res->num_rows > 0;
    
    // Check if test_cases table exists
    $query = "SHOW TABLES LIKE 'test_cases'";
    $res = $conn->query($query);
    $result['test_cases_table_exists'] = $res->num_rows > 0;
    
    // Check assignments table structure
    $query = "DESCRIBE assignments";
    $res = $conn->query($query);
    $result['assignments_columns'] = [];
    while ($row = $res->fetch_assoc()) {
        $result['assignments_columns'][] = $row['Field'];
    }
    
    // Check tasks table structure (if exists)
    if ($result['tasks_table_exists']) {
        $query = "DESCRIBE tasks";
        $res = $conn->query($query);
        $result['tasks_columns'] = [];
        while ($row = $res->fetch_assoc()) {
            $result['tasks_columns'][] = $row['Field'];
        }
    }
    
    // Check test_cases table structure
    $query = "DESCRIBE test_cases";
    $res = $conn->query($query);
    $result['test_cases_columns'] = [];
    while ($row = $res->fetch_assoc()) {
        $result['test_cases_columns'][] = $row['Field'];
    }
    
    // Count assignments
    $query = "SELECT COUNT(*) as count FROM assignments";
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    $result['assignments_count'] = (int)$row['count'];
    
    // Count tasks (if table exists)
    if ($result['tasks_table_exists']) {
        $query = "SELECT COUNT(*) as count FROM tasks";
        $res = $conn->query($query);
        $row = $res->fetch_assoc();
        $result['tasks_count'] = (int)$row['count'];
    }
    
    echo json_encode([
        'ok' => true,
        'result' => $result
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
