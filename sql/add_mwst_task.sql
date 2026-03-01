-- Add MwSt-Rechner Task to Assignment #21
-- Task uses input() to calculate gross price from net price

USE pythonide;

-- Get the next position for assignment #21
SET @next_position = (SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE assignment_id = 21);

-- Insert MwSt calculation task
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
    'MwSt-Rechner (19%)',                                   -- title
    '<div class="test-requirements-section"><h3>Test-Anforderungen</h3><table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody><tr><td>INPUT</td><td>Verwendet input() für Nettopreis-Eingabe</td></tr><tr><td>CODE</td><td>Korrekte MwSt-Berechnung (19%)</td></tr><tr><td>OUTPUT</td><td>Bruttopreis wird angezeigt</td></tr></tbody></table></div>',  -- description
    @next_position,                                         -- position
    5,                                                      -- max_attempts
    1,                                                      -- iterations_count
    1,                                                      -- show_solution
    1,                                                      -- show_solution_code
    NULL,                                                   -- min_keywords_required
    'code_completion',                                      -- problem_type
    '# MwSt-Rechner: Berechne den Bruttopreis aus dem Nettopreis\n# MwSt-Satz: 19%\n\n# Hier Code schreiben\n',  -- code_template
    'Verwende input() um den Nettopreis einzulesen',       -- hint1
    'Formel: Bruttopreis = Nettopreis * 1.19',             -- hint2
    'Nutze float() um die Eingabe in eine Zahl zu konvertieren',  -- hint3
    'In Deutschland beträgt die Standard-Mehrwertsteuer (MwSt) 19%. Der Bruttopreis ist der Nettopreis plus 19% MwSt.',  -- stoff
    '',                                                     -- expected_output (validated via test_cases)
    '[
        {
            "type": "code_check",
            "description": "Verwendet input() für Eingabe",
            "pattern": "input\\\\s*\\\\(",
            "required": true,
            "hint": "Nutze input() um den Nettopreis vom Benutzer zu erfragen"
        },
        {
            "type": "code_check",
            "description": "Konvertiert Eingabe zu float oder int",
            "pattern": "(float|int)\\\\s*\\\\(",
            "required": true,
            "hint": "Konvertiere die Eingabe mit float() oder int() in eine Zahl"
        },
        {
            "type": "code_check",
            "description": "Multipliziert mit 1.19 oder addiert 0.19",
            "pattern": "(\\\\*\\\\s*1\\\\.19|\\\\+.*0\\\\.19|\\\\*\\\\s*0\\\\.19)",
            "required": true,
            "hint": "Berechne den Bruttopreis: Nettopreis * 1.19"
        },
        {
            "type": "code_check",
            "description": "Gibt Ergebnis mit print() aus",
            "pattern": "print\\\\s*\\\\(",
            "required": true,
            "hint": "Gib den Bruttopreis mit print() aus"
        }
    ]',  -- test_cases
    '# Musterlösung: MwSt-Rechner\n\n# Nettopreis vom Benutzer einlesen\nnetto = input("Nettopreis in Euro: ")\n\n# In Zahl konvertieren\nnetto = float(netto)\n\n# Bruttopreis berechnen (19% MwSt)\nbrutto = netto * 1.19\n\n# Ergebnis ausgeben\nprint(f"Bruttopreis: {brutto:.2f} Euro")\nprint(f"(enthält {netto * 0.19:.2f} Euro MwSt)")',  -- solution_code
    'code',                                                 -- task_type
    '# MwSt-Rechner mit input()

Schreibe ein Python-Programm, das einen **Nettopreis** vom Benutzer einliest und den **Bruttopreis** (inkl. 19% MwSt) berechnet und ausgibt.

## Anforderungen:

1. **Eingabe**: Verwende `input()` um nach dem Nettopreis zu fragen
2. **Berechnung**: Berechne den Bruttopreis mit 19% MwSt
   - Formel: `Bruttopreis = Nettopreis × 1,19`
3. **Ausgabe**: Gib den Bruttopreis aus

## Beispiel-Ausführung:

```
Nettopreis in Euro: 100
Bruttopreis: 119.00 Euro
```

```
Nettopreis in Euro: 49.99
Bruttopreis: 59.49 Euro
```

## Tipps:

- Der MwSt-Satz in Deutschland beträgt **19%**
- Nettopreis × 1,19 = Bruttopreis
- Oder: Nettopreis + (Nettopreis × 0,19) = Bruttopreis
- Nutze `float()` für Dezimalzahlen
- Formatiere die Ausgabe mit `:.2f` für 2 Nachkommastellen',  -- task_text
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
    'MwSt-Rechner (19%)' as 'Title',
    @next_position as 'Position',
    'Task successfully added to Assignment #21' as 'Status';

-- Show all tasks in assignment #21 for verification
SELECT 
    id,
    position,
    title,
    task_type,
    problem_type,
    created_at
FROM tasks
WHERE assignment_id = 21
ORDER BY position;
