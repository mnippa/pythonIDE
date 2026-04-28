-- Quickfix: user_assignments.due_date ist exakt 1 Tag vor assignments.due_date
-- Ziel: due_date auf assignments.due_date korrigieren
--
-- Anwendung:
-- 1) Erst die Preview-SELECTs prüfen
-- 2) Dann UPDATE in Transaction ausführen
-- 3) COMMIT nur wenn row_count plausibel

-- ==========================================
-- PREVIEW: betroffene Datensätze ansehen
-- ==========================================
SELECT
  ua.id,
  ua.user_id,
  ua.assignment_id,
  ua.due_date AS user_due_date,
  a.due_date AS assignment_due_date,
  TIMESTAMPDIFF(HOUR, ua.due_date, a.due_date) AS diff_hours
FROM user_assignments ua
JOIN assignments a ON a.id = ua.assignment_id
WHERE ua.due_date IS NOT NULL
  AND a.due_date IS NOT NULL
  AND ua.due_date = DATE_SUB(a.due_date, INTERVAL 1 DAY)
ORDER BY ua.assignment_id, ua.user_id;

-- Optional: nur ein bestimmtes Assignment prüfen (ID einsetzen)
-- SELECT *
-- FROM user_assignments ua
-- JOIN assignments a ON a.id = ua.assignment_id
-- WHERE ua.assignment_id = 123
--   AND ua.due_date = DATE_SUB(a.due_date, INTERVAL 1 DAY);

-- ==========================================
-- UPDATE: Korrektur durchführen
-- ==========================================
START TRANSACTION;

UPDATE user_assignments ua
JOIN assignments a ON a.id = ua.assignment_id
SET ua.due_date = a.due_date
WHERE ua.due_date IS NOT NULL
  AND a.due_date IS NOT NULL
  AND ua.due_date = DATE_SUB(a.due_date, INTERVAL 1 DAY);

-- Kontrollwert nach UPDATE
SELECT ROW_COUNT() AS updated_rows;

-- Optional: nachkontrollieren, ob noch Kandidaten offen sind
SELECT COUNT(*) AS remaining_shifted_rows
FROM user_assignments ua
JOIN assignments a ON a.id = ua.assignment_id
WHERE ua.due_date IS NOT NULL
  AND a.due_date IS NOT NULL
  AND ua.due_date = DATE_SUB(a.due_date, INTERVAL 1 DAY);

-- Bei Erfolg:
-- COMMIT;
-- Bei Zweifel:
-- ROLLBACK;
