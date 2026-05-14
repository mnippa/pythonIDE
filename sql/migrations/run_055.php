<?php
/**
 * Migration 055: Create support_tickets table for simple admin support system
 * 
 * Allows students to generate support tickets for admins to review their assignments
 * Idempotent - safe to run multiple times
 */

require_once __DIR__ . '/../../config/database.php';

$useBetaLiveDb = getenv('USE_BETA_LIVE_DB');
if ($useBetaLiveDb) {
    require_once __DIR__ . '/../../config/database.beta_live.local.php';
}

try {
    $conn = $useBetaLiveDb ? getBetaLiveDbConnection() : getDbConnection();
    
    echo "=== Migration 055: Create support_tickets table ===\n";
    echo $useBetaLiveDb ? "Target DB: BETA/LIVE\n" : "Target DB: LOCAL\n";
    
    // Check if table already exists
    $checkSql = "SHOW TABLES LIKE 'support_tickets'";
    $result = $conn->query($checkSql);
    
    if ($result && $result->num_rows > 0) {
        echo "✓ Table support_tickets already exists\n";
        exit(0);
    }
    
    // Create table
    $createSql = "
        CREATE TABLE support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT(10) UNSIGNED NOT NULL,
            assignment_id INT(10) UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_created (created_at),
            INDEX idx_user_assignment (user_id, assignment_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if (!$conn->query($createSql)) {
        throw new Exception('Create table failed: ' . $conn->error);
    }
    
    echo "✓ Table support_tickets created successfully\n";
    
} catch (Exception $e) {
    echo "✗ Migration 055 failed: " . $e->getMessage() . "\n";
    exit(1);
}
