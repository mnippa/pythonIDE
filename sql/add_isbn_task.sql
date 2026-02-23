-- Add ISBN Validation Task to Assignment #21
-- Task checks output using REGEX validation

USE pythonide;

-- Get the next position for assignment #21
SET @next_position = (SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE assignment_id = 21);

-- Insert ISBN validation task
INSERT INTO tasks (
    assignment_id,
    title,
    description,
    position,
    max_attempts,
    iterations_count,
    show_solution,
    show_solution_code,
    min_keywords_required,
    problem_type,
    code_template,
    hint1,
    hint2,
    hint3,
    stoff,
    expected_output,
    test_cases,
    solution_code,
    task_type,
    task_text,
    question_text,
    image_url,
    correct_answer,
    variable_overrides,
    randomizer_code
) VALUES (
    21,                                                     -- assignment_id
    'ISBN Validierung',                                     -- title
    '<div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>OUTPUT</td><td>Pattern Match (Regex)</td></tr></tbody></table></div>',  -- description
    @next_position,                                         -- position
    3,                                                      -- max_attempts
    1,                                                      -- iterations_count
    1,                                                      -- show_solution
    1,                                                      -- show_solution_code
    NULL,                                                   -- min_keywords_required
    'code_completion',                                      -- problem_type
    '# Gib eine gültige ISBN aus\n# Format: ISBN 978-3-16-148410-0\n\nprint("ISBN ")',  -- code_template
    'Eine ISBN-13 beginnt mit 978 oder 979',               -- hint1
    'Das Format ist: ISBN XXX-X-XX-XXXXXX-X',              -- hint2
    'Verwende print() für die Ausgabe',                    -- hint3
    'ISBN (International Standard Book Number) ist eine weltweit eindeutige Produktkennzeichnung für Bücher.',  -- stoff
    '',                                                     -- expected_output (not used for regex validation)
    '[{"type":"output","validation_mode":"pattern","pattern":"^ISBN\\\\s+(978|979)-\\\\d{1,5}-\\\\d{1,7}-\\\\d{1,7}-\\\\d{1}$","description":"Gültige ISBN-13 im Format: ISBN 978-X-XX-XXXXXX-X"}]',  -- test_cases
    '# Musterlösung\nprint("ISBN 978-3-16-148410-0")',     -- solution_code
    'code',                                                 -- task_type
    'Schreibe ein Python-Programm, das eine gültige ISBN-13 ausgibt. Die ISBN muss folgendes Format haben:\n\n**Format:** `ISBN 978-X-XX-XXXXXX-X` oder `ISBN 979-X-XX-XXXXXX-X`\n\n**Beispiele:**\n- `ISBN 978-3-16-148410-0`\n- `ISBN 979-1-23-456789-5`\n\n**Hinweise:**\n- Die ISBN muss mit "ISBN " (mit Leerzeichen) beginnen\n- Die Präfix-Gruppe ist entweder 978 oder 979\n- Die Zahlengruppen sind durch Bindestriche getrennt',  -- task_text
    '',                                                     -- question_text (empty for code tasks)
    NULL,                                                   -- image_url
    NULL,                                                   -- correct_answer
    NULL,                                                   -- variable_overrides
    NULL                                                    -- randomizer_code
);

-- Get the ID of the newly inserted task
SET @new_task_id = LAST_INSERT_ID();

-- Display success message
SELECT 
    @new_task_id as 'Task ID',
    'ISBN Validierung' as 'Title',
    @next_position as 'Position',
    'Task successfully added to Assignment #21' as 'Status';

-- Show all tasks in assignment #21 for verification
SELECT 
    id,
    position,
    title,
    task_type,
    created_at
FROM tasks
WHERE assignment_id = 21
ORDER BY position;
