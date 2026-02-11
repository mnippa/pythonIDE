-- Migration 001: Add Teams and Assignment Management
-- Execute: mysql -u root -p python_ide < sql/migrations/001_add_teams.sql

-- Teams Tabelle
CREATE TABLE IF NOT EXISTS teams (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  is_active BOOLEAN DEFAULT 1,
  created_at DATETIME DEFAULT NOW(),
  updated_at DATETIME DEFAULT NOW() ON UPDATE NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User-Team-Zuordnung (1 User = 1 Team)
ALTER TABLE users ADD COLUMN team_id INT NULL;
ALTER TABLE users ADD CONSTRAINT fk_users_team 
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL;

-- Assignment-Zuweisungen (Viele-zu-Viele)
-- Entweder an einzelne User ODER an ganzes Team
CREATE TABLE IF NOT EXISTS user_assignments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  assignment_id INT NOT NULL,
  user_id INT NULL,              -- Einzelner User ODER
  team_id INT NULL,               -- Ganzes Team
  assigned_at DATETIME DEFAULT NOW(),
  assigned_by INT,                -- Welcher Admin hat zugewiesen
  due_date DATETIME NULL,         -- Optional: Deadline
  FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
  -- Entweder user_id ODER team_id muss gesetzt sein
  CHECK ((user_id IS NOT NULL AND team_id IS NULL) OR (user_id IS NULL AND team_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Index für schnellere Queries
CREATE INDEX idx_user_assignments_user ON user_assignments(user_id);
CREATE INDEX idx_user_assignments_team ON user_assignments(team_id);
CREATE INDEX idx_user_assignments_assignment ON user_assignments(assignment_id);
CREATE INDEX idx_users_team ON users(team_id);

-- Beispiel-Teams anlegen
INSERT INTO teams (name, description, is_active) VALUES
  ('WS 24/25 - Gruppe A', 'Wintersemester 2024/25 - Gruppe A', 1),
  ('WS 24/25 - Gruppe B', 'Wintersemester 2024/25 - Gruppe B', 1),
  ('SS 25 - Anfänger', 'Sommersemester 2025 - Anfänger-Kurs', 1);
