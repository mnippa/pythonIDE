-- Migration: Add assignment_tasks table
-- This table stores individual tasks within an assignment
-- Each assignment can have multiple tasks

USE pythonide;

CREATE TABLE IF NOT EXISTS assignment_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    position INT DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('code_completion', 'multiple_choice', 'fill_in_blank') DEFAULT 'code_completion',
    template MEDIUMTEXT,
    hint1 TEXT,
    hint2 TEXT,
    hint3 TEXT,
    stoff TEXT,
    validation_mode ENUM('test-mode', 'intelligent') DEFAULT 'test-mode',
    test_cases JSON,
    solution MEDIUMTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
