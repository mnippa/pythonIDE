-- 026_assignment_schedule_and_team_defaults.sql
-- Safe schema extension for assignment timeline management and team defaults.
-- Designed for production databases with existing users/assignments.

-- 1) Assignment timeline fields
ALTER TABLE assignments
    ADD COLUMN IF NOT EXISTS available_from DATETIME NULL AFTER is_active,
    ADD COLUMN IF NOT EXISTS due_date DATETIME NULL AFTER available_from,
    ADD COLUMN IF NOT EXISTS hard_deadline DATETIME NULL AFTER due_date,
    ADD COLUMN IF NOT EXISTS allow_late_submission TINYINT(1) NOT NULL DEFAULT 1 AFTER hard_deadline;

-- 2) User assignment late-submission marker
ALTER TABLE user_assignments
    ADD COLUMN IF NOT EXISTS is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER submitted_at;

-- 3) Team assignment defaults (Variante B materialization source)
CREATE TABLE IF NOT EXISTS team_assignment_defaults (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    assignment_id INT(10) UNSIGNED NOT NULL,
    assigned_by INT(10) UNSIGNED NULL,
    due_date DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_team_assignment_defaults (team_id, assignment_id),
    KEY idx_tad_team (team_id),
    KEY idx_tad_assignment (assignment_id),
    CONSTRAINT fk_tad_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    CONSTRAINT fk_tad_assignment FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    CONSTRAINT fk_tad_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Duplicate protection for user-specific assignment materialization
-- Note: allows many NULL user_id rows by MySQL behavior, but protects non-NULL user assignments.
ALTER TABLE user_assignments
    ADD UNIQUE KEY uq_user_assignment_direct (assignment_id, user_id);

-- 5) Helpful indices for timeline queries
CREATE INDEX idx_assignments_is_active_dates ON assignments(is_active, available_from, due_date, hard_deadline);
CREATE INDEX idx_user_assignments_user_status ON user_assignments(user_id, status);
CREATE INDEX idx_user_assignments_user_late ON user_assignments(user_id, is_late);

-- 6) Backfill defaults from existing team-based assignments (legacy rows)
INSERT IGNORE INTO team_assignment_defaults (team_id, assignment_id, assigned_by, due_date, is_active)
SELECT ua.team_id, ua.assignment_id, ua.assigned_by, ua.due_date, 1
FROM user_assignments ua
WHERE ua.team_id IS NOT NULL;

UPDATE team_assignment_defaults tad
INNER JOIN assignments a ON a.id = tad.assignment_id
SET tad.due_date = COALESCE(tad.due_date, a.due_date);

-- 7) Materialize assignments for current users in those teams
INSERT IGNORE INTO user_assignments (assignment_id, user_id, assigned_by, due_date, status)
SELECT tad.assignment_id, u.id, tad.assigned_by, COALESCE(tad.due_date, a.due_date), 'assigned'
FROM team_assignment_defaults tad
INNER JOIN users u ON u.team_id = tad.team_id
INNER JOIN assignments a ON a.id = tad.assignment_id
WHERE tad.is_active = 1;
