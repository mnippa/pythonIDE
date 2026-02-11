-- Create test assignment for task import testing

USE pythonide;

-- Insert test assignment "Python Grundlagen"
INSERT INTO assignments (title, description, created_by, is_active, difficulty, created_at, updated_at) 
VALUES (
    'Python Grundlagen',
    'Lernen Sie die Grundlagen der Python-Programmierung: Variablen, Schleifen, Funktionen und Algorithmen.',
    1,  -- Admin user ID
    TRUE,
    'beginner',
    NOW(),
    NOW()
);

-- Get the ID (will likely be 1 if this is the first assignment)
SELECT LAST_INSERT_ID() as assignment_id;
