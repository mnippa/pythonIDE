-- Python IDE Database Schema
-- Created: 2026-02-07

CREATE DATABASE IF NOT EXISTS pythonide CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pythonide;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL DEFAULT '',
    last_name VARCHAR(80) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    last_opened_project_id INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_last_opened_project_id (last_opened_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Projects table (user's saved scripts)
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    code MEDIUMTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignments table (tasks created by admins)
CREATE TABLE IF NOT EXISTS assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    code_template MEDIUMTEXT,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    time_limit_minutes INT DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_is_active (is_active),
    INDEX idx_created_by (created_by),
    INDEX idx_difficulty (difficulty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test cases for assignments
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

-- User assignment tracking
CREATE TABLE IF NOT EXISTS user_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    assignment_id INT UNSIGNED NOT NULL,
    status ENUM('assigned', 'in_progress', 'submitted', 'passed', 'failed') DEFAULT 'assigned',
    current_code MEDIUMTEXT,
    submitted_at TIMESTAMP NULL DEFAULT NULL,
    test_results JSON,
    attempts INT DEFAULT 0,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_assignment (user_id, assignment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert test users with realistic data
-- Note: All passwords are hashed with bcrypt

-- Admin user (email: admin@pythonide.local, password: admin123)
INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES
('admin@pythonide.local', 'Sarah', 'Schmidt', '$2y$10$0BDRET8OScPxeaK7xPvP1.dp7tcvVWWCaLLfWh7UIP.WyWauGx4L6', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Regular test users (all passwords: test123)
INSERT INTO users (email, first_name, last_name, password_hash, role) VALUES
('max.mueller@example.com', 'Max', 'Müller', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('anna.schulz@example.com', 'Anna', 'Schulz', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('tom.weber@example.com', 'Tom', 'Weber', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user'),
('lisa.fischer@example.com', 'Lisa', 'Fischer', '$2y$10$h8jBmVqm9e2E3DdLYgohi.J8eNwPl95XTST0urazUo6S4dxlKKS.6', 'user')
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample projects for test users
INSERT INTO projects (user_id, name, description, code) VALUES
(2, 'Hallo Welt', 'Mein erstes Python-Programm', 'print("Hallo Welt!")\nprint("Willkommen bei Python IDE")'),
(2, 'Fibonacci Folge', 'Berechnet Fibonacci-Zahlen', 'def fibonacci(n):\n    if n <= 1:\n        return n\n    return fibonacci(n-1) + fibonacci(n-2)\n\nfor i in range(10):\n    print(f"F({i}) = {fibonacci(i)}")'),
(3, 'Liste sortieren', 'Sortiert eine Liste von Zahlen', 'numbers = [64, 34, 25, 12, 22, 11, 90]\nprint("Unsortiert:", numbers)\nnumbers.sort()\nprint("Sortiert:", numbers)'),
(3, 'Primzahlen', 'Findet alle Primzahlen bis 100', 'def ist_primzahl(n):\n    if n < 2:\n        return False\n    for i in range(2, int(n**0.5) + 1):\n        if n % i == 0:\n            return False\n    return True\n\nprimzahlen = [n for n in range(2, 101) if ist_primzahl(n)]\nprint("Primzahlen bis 100:", primzahlen)'),
(4, 'Temperatur Umrechner', 'Celsius zu Fahrenheit', 'def celsius_zu_fahrenheit(celsius):\n    return (celsius * 9/5) + 32\n\ncelsius = 25\nfahrenheit = celsius_zu_fahrenheit(celsius)\nprint(f"{celsius}°C = {fahrenheit}°F")')
ON DUPLICATE KEY UPDATE id=id;

