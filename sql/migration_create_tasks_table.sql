-- Migration: Create tasks table and restructure
-- Assignments = Container (e.g., "Python Grundlagen Kurs")
-- Tasks = Individual exercises within an assignment

USE pythonide;

-- Rename current assignments to keep data safe
RENAME TABLE assignments TO assignments_old;

-- Create new assignments table (Container/Course)
CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_created_by (created_by),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create tasks table (Individual exercises)
CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    position INT DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    code_template MEDIUMTEXT,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    time_limit_minutes INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_position (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update test_cases to reference tasks instead of assignments
ALTER TABLE test_cases DROP FOREIGN KEY test_cases_ibfk_1;
ALTER TABLE test_cases CHANGE assignment_id task_id INT UNSIGNED NOT NULL;
ALTER TABLE test_cases ADD FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE;

-- Update user_assignments to stay with assignments (container level)
-- This remains unchanged as users are assigned to courses/assignments, not individual tasks
