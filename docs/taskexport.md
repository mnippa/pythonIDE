# Task Export Format (JSON)

This document defines the JSON format for importing a single task into an existing assignment.

## Overview
- One JSON file = one task.
- The assignment is selected in the UI; the JSON does not include `assignment_id`.
- All fields are optional unless marked as required.

## Required Fields
- `version` (string)
- `title` (string)

## Optional Fields
- `position` (number) - If omitted, it will be appended at the end.
- `problem_type` (string) - Examples: `code_completion`, `code_fix`, `multiple_choice`, `essay`.
- `description` (string)
- `code_template` (string)
- `hint` (string)
- `hint1` (string)
- `hint2` (string)
- `hint3` (string)
- `stoff` (string)
- `expected_output` (string)
- `solution_code` (string)
- `max_attempts` (number)
- `validation_mode` (string) - Example: `test-mode`, `intelligent`.
- `test_cases` (array) - Stored as JSON in the task record.

## Example
```json
{
  "version": "1.0",
  "position": 1,
  "title": "Primzahlen bis 100",
  "problem_type": "code_completion",
  "description": "Schreiben Sie ein Programm, das alle Primzahlen von 2 bis 100 findet und ausgibt.",
  "code_template": "# Finden Sie alle Primzahlen von 2 bis 100\n# und geben Sie jede Primzahl aus\n\n",
  "hint1": "Verwenden Sie eine äußere Schleife für die Zahlen von 2 bis 100.",
  "hint2": "Prüfen Sie für jede Zahl, ob es einen Teiler gibt.",
  "hint3": "Optimieren Sie die Prüfung mit der Quadratwurzel.",
  "stoff": "Kapitel: Schleifen, Modulo-Operator, Primzahlen-Algorithmus",
  "validation_mode": "intelligent",
  "solution_code": "for zahl in range(2, 101):\n    ist_primzahl = True\n    for teiler in range(2, zahl):\n        if zahl % teiler == 0:\n            ist_primzahl = False\n            break\n    if ist_primzahl:\n        print(zahl)",
  "test_cases": [
    {
      "type": "OUTPUT",
      "test_cases": [
        {
          "input": "",
          "expected": "2\n3\n5\n7\n11\n13\n17\n19\n23\n29\n31\n37\n41\n43\n47\n53\n59\n61\n67\n71\n73\n79\n83\n89\n97"
        }
      ]
    }
  ]
}
```

## Notes for AI Generation
- Always include `version` and `title`.
- Use `problem_type` values that match the system.
- Keep `test_cases` as an array that can be stored as JSON without transformation.
