-- Reset all user task attempts for testing
-- WARNING: This will delete all progress!

-- Option 1: Delete all user_tasks (complete reset)
-- TRUNCATE TABLE user_tasks;

-- Option 2: Reset only quiz tasks (keep code tasks)
UPDATE user_tasks ut
INNER JOIN tasks t ON t.id = ut.task_id
SET ut.status = 'unbearbeitet',
    ut.attempts = 0,
    ut.selected_options = NULL,
    ut.text_answer = NULL,
    ut.variable_values = NULL
WHERE t.task_type IN ('single_choice', 'multiple_choice', 'free_text', 'code_reading');

-- Option 3: Reset specific assignment (replace 7 with your assignment_id)
-- UPDATE user_tasks ut
-- INNER JOIN tasks t ON t.id = ut.task_id
-- SET ut.status = 'unbearbeitet',
--     ut.attempts = 0,
--     ut.selected_options = NULL,
--     ut.text_answer = NULL,
--     ut.variable_values = NULL
-- WHERE t.assignment_id = 7;
