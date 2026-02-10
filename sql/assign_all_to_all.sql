-- Allen registrierten Nutzern alle Assignments zuweisen
-- Created: 2026-02-09
-- Purpose: Löscht alte Zuweisungen und weist allen Nutzern alle vorhandenen Assignments zu

USE pythonide;

-- Alle alten Zuweisungen und Ergebnisse löschen
TRUNCATE TABLE user_assignments;

-- Allen Nutzern (außer Admins) alle Assignments zuweisen
INSERT INTO user_assignments (user_id, assignment_id, status, assigned_at)
SELECT u.id, a.id, 'assigned', NOW()
FROM users u
CROSS JOIN assignments a
WHERE u.role = 'user'  -- Nur normale Nutzer, keine Admins
  AND a.is_active = TRUE;  -- Nur aktive Assignments

-- Ausgabe der Ergebnisse
SELECT 'Alle Assignments wurden allen Nutzern zugewiesen!' as status;
SELECT COUNT(DISTINCT user_id) as 'Anzahl Nutzer', 
       COUNT(DISTINCT assignment_id) as 'Anzahl Assignments',
       COUNT(*) as 'Gesamt Zuweisungen'
FROM user_assignments;
