-- Migration: Add user_tasks table for tracking individual task progress
-- Purpose: Store per-task attempts, status, code, and hints revealed

CREATE TABLE IF NOT EXISTS user_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NOT NULL,
    status ENUM('unbearbeitet', 'in-progress', 'passed', 'failed') DEFAULT 'unbearbeitet',
    attempts INT DEFAULT 0,
    current_code MEDIUMTEXT,
    hints_revealed JSON COMMENT 'Array of revealed hint numbers: [1, 2, 3]',
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_task (user_id, task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_task_id (task_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
