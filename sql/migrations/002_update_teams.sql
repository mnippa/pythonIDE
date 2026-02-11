-- Update Teams to new naming and add more semesters
-- Run: php sql/migrations/run_002.php

-- Update existing teams
UPDATE teams SET name = 'WiSe 25/26', description = 'Wintersemester 2025/2026' WHERE id = 1;
UPDATE teams SET name = 'SoSe 26', description = 'Sommersemester 2026' WHERE id = 2;
UPDATE teams SET name = 'SoSe 27', description = 'Sommersemester 2027' WHERE id = 3;

-- Add more teams
INSERT INTO teams (name, description, is_active) VALUES
  ('SoSe 28', 'Sommersemester 2028', 1)
ON DUPLICATE KEY UPDATE name=name;

-- Assign all existing users to WiSe 25/26
UPDATE users SET team_id = 1 WHERE team_id IS NULL;
