<?php
/**
 * Execute Migration 001: Add Teams
 * Run via: php sql/migrations/run_001.php
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    
    echo "Starting migration 001: Add Teams...\n";
    
    // Create teams table
    echo "Creating teams table...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS teams (
          id INT PRIMARY KEY AUTO_INCREMENT,
          name VARCHAR(100) NOT NULL,
          description TEXT,
          is_active BOOLEAN DEFAULT 1,
          created_at DATETIME DEFAULT NOW(),
          updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Add team_id to users
    echo "Adding team_id column to users...\n";
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS team_id INT NULL");
    
    // Add foreign key
    echo "Adding foreign key constraint...\n";
    try {
        $conn->query("
            ALTER TABLE users 
            ADD CONSTRAINT fk_users_team 
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL
        ");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }
    
    // Create user_assignments table
    echo "Creating user_assignments table...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_assignments (
          id INT PRIMARY KEY AUTO_INCREMENT,
          assignment_id INT NOT NULL,
          user_id INT NULL,
          team_id INT NULL,
          assigned_at DATETIME DEFAULT NOW(),
          assigned_by INT,
          due_date DATETIME NULL,
          FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
          FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
          CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create indexes
    echo "Creating indexes...\n";
    try {
        $conn->query("CREATE INDEX idx_user_assignments_user ON user_assignments(user_id)");
    } catch (Exception $e) {}
    
    try {
        $conn->query("CREATE INDEX idx_user_assignments_team ON user_assignments(team_id)");
    } catch (Exception $e) {}
    
    try {
        $conn->query("CREATE INDEX idx_user_assignments_assignment ON user_assignments(assignment_id)");
    } catch (Exception $e) {}
    
    try {
        $conn->query("CREATE INDEX idx_users_team ON users(team_id)");
    } catch (Exception $e) {}
    
    // Insert sample teams
    echo "Inserting sample teams...\n";
    $conn->query("
        INSERT INTO teams (name, description, is_active) VALUES
          ('WS 24/25 - Gruppe A', 'Wintersemester 2024/25 - Gruppe A', 1),
          ('WS 24/25 - Gruppe B', 'Wintersemester 2024/25 - Gruppe B', 1),
          ('SS 25 - Anfänger', 'Sommersemester 2025 - Anfänger-Kurs', 1)
        ON DUPLICATE KEY UPDATE name=name
    ");
    
    echo "\n✅ Migration 001 completed successfully!\n";
    echo "Teams created: 3\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
