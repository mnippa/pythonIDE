<?php
/**
 * Debug: Check user_tasks table structure
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Checking user_tasks table structure...\n\n";
    
    $result = $conn->query("SHOW COLUMNS FROM user_tasks");
    
    if ($result) {
        echo "Columns in user_tasks:\n";
        echo str_repeat("-", 80) . "\n";
        printf("%-25s %-30s %-10s %-10s\n", "Field", "Type", "Null", "Key");
        echo str_repeat("-", 80) . "\n";
        
        while ($row = $result->fetch_assoc()) {
            printf("%-25s %-30s %-10s %-10s\n", 
                $row['Field'], 
                $row['Type'], 
                $row['Null'], 
                $row['Key']
            );
        }
        echo str_repeat("-", 80) . "\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
