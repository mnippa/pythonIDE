# Task Export Format (JSON)

## Current-state note

This file is the reference for the single-task JSON import shape, but it is not a full authoring guide.

Read first when creating new tasks:
- [TASK_AUTHORING_GUIDE.md](TASK_AUTHORING_GUIDE.md)
- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)

Important:
- for new tasks, prefer `task_text` for the short student-facing instruction
- use `description` for detailed conditions and checking notes
- folder-based tasks may require files outside the JSON payload

This document defines the JSON format for importing a single task into an existing assignment.

## Overview
- One JSON file = one task.
- The assignment is selected in the UI; the JSON does not include `assignment_id`.
- All fields are optional unless marked as required.

## Required Fields
- `version` (string, currently `3.0`)
- `title` (string)
- `task_type` (string) - One of: `code`, `code_ui`, `single_choice`, `multiple_choice`, `free_text`, `code_reading`, `code_random_complex`.

### Additional required rule for `code_random_complex`

For `task_type = code_random_complex`, `code_template` must include at least one of:
- `values` dict usage, or
- placeholder syntax `{varName}`.

Without this, task creation during import fails with HTTP 400 on systems that validate this rule.

Practical recommendation:
- when doing editorial-only updates, keep `code_template` technical and do text changes in `task_text`/`description`.

## Optional Fields
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
  "version": "3.0",
  "task_type": "code",
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
- Always include `version`, `task_type`, and `title`.
- Use `version: "3.0"` for compatibility with current importer.
- Use `problem_type` values that match the system.
- Keep `test_cases` as an array that can be stored as JSON without transformation.

## Best Practices: Feldverwendung (verbindlich)

### `title`
- Wird in der Navigation/Liste angezeigt.
- Kurz und eindeutig benennen (z. B. „MwSt-Rechner“).

### `task_text`
- Wird oben zentral als student-facing Aufgabenstellung angezeigt.
- Für **alle** Task-Typen verwenden (`code`, `code_ui`, `single_choice`, `multiple_choice`, `free_text`, ...).
- **Kurz und prägnant** halten (nur Kernauftrag).

### `question_text`
- Gilt als **deprecated**.
- Für neue Aufgaben nicht inhaltlich nutzen.
- Wenn vorhanden, leer lassen oder ignorieren.

### `description`
- Enthält die **Details** zur Aufgabe.
- Hier stehen Randbedingungen, Eingabe-/Ausgabeformat, Beispiele und Hinweise zur Prüfmethode.
- Typisch: Erläuterung, was die Tests prüfen.

### `stoff`
- Enthält Lernhinweise/Didaktik.
- Bevorzugt als **HTML** speichern (z. B. `<h4>`, `<ul>`, `<li>`), damit die Darstellung im UI strukturiert ist.

### Empfohlene Reihenfolge im Inhalt
1. `task_text` → kurz: Was ist zu tun?
2. `description` → detailliert: Wie/unter welchen Bedingungen?
3. `stoff` → Lernhilfe und methodische Hinweise (HTML)
4. `test_cases` → automatische Prüfung

### Beispiel
```json
{
  "title": "MwSt-Rechner",
  "task_text": "Berechne für Nettopreis und MwSt-Satz den MwSt-Betrag und den Bruttopreis.",
  "question_text": "",
  "description": "Nutze die Eingabefelder netto/mwst. Berechne mwst_betrag und brutto. Gib alle Ergebnisse auf 2 Nachkommastellen aus. Die Prüfung kontrolliert Variablenwerte und Ausgabezuordnung.",
  "stoff": "<h4>Prozentrechnung</h4><ul><li>mwst_betrag = netto * (mwst/100)</li><li>brutto = netto + mwst_betrag</li></ul>",
  "test_cases": [...]
}
```

## Intelligent `vars` Input Convention
- For deterministic testing of programs using `input()`, use `test_cases` type `intelligent` with `mode: "vars"` and a `randomizer_code` that defines `values`.
- Input placeholders in `values` should use `INPUT_01`, `INPUT_02`, ..., `INPUT_99`.
- During check, `input()` is overridden and consumes these values in numeric order.
- Example randomizer snippet:
```python
import random
values = {
  'INPUT_01': random.randint(1, 50),
  'INPUT_02': random.randint(1, 50)
}
```
