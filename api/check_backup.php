<?php
/**
 * Check for assignments_old table and data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
    
    $result = [];
    
    // Check if assignments_old exists
    $query = "SHOW TABLES LIKE 'assignments_old'";
    $res = $conn->query($query);
    $result['assignments_old_exists'] = $res->num_rows > 0;
    
    if ($result['assignments_old_exists']) {
        // Count records in assignments_old
        $query = "SELECT COUNT(*) as count FROM assignments_old";
        $res = $conn->query($query);
        $row = $res->fetch_assoc();
        $result['assignments_old_count'] = (int)$row['count'];
        
        // Get some sample data
        $query = "SELECT id, title, difficulty FROM assignments_old LIMIT 5";
        $res = $conn->query($query);
        $result['assignments_old_sample'] = [];
        while ($row = $res->fetch_assoc()) {
            $result['assignments_old_sample'][] = $row;
        }
        
        // Check structure
        $query = "DESCRIBE assignments_old";
        $res = $conn->query($query);
        $result['assignments_old_columns'] = [];
        while ($row = $res->fetch_assoc()) {
            $result['assignments_old_columns'][] = $row['Field'];
        }
    }
    
    // Count current assignments
    $query = "SELECT COUNT(*) as count FROM assignments";
    $res = $conn->query($query);
    $row = $res->fetch_assoc();
    $result['assignments_count'] = (int)$row['count'];
    
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
