-- Restore exact old structure
USE pythonide;

-- Drop new tables
DROP TABLE IF EXISTS test_cases;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS assignments;

-- Restore assignments from backup
RENAME TABLE assignments_old TO assignments;

-- Restore tasks from backup
RENAME TABLE tasks_old TO tasks;

-- Restore test_cases table with assignment_id (not task_id)
CREATE TABLE IF NOT EXISTS test_cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT UNSIGNED NOT NULL,
    description VARCHAR(255),
    test_input JSON,
    expected_output TEXT,
    is_hidden BOOLEAN DEFAULT FALSE,
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_order (order_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
