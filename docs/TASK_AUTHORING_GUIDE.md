# Task Authoring Guide

## Purpose

This document is the practical guide for creating new tasks in PythonIDE.
It is intentionally separate from the broader platform and runtime documentation.

Read this first when the goal is:
- create a new task
- revise task wording or field usage
- choose a validation strategy
- decide whether a task needs folder structure or extra files

For overall platform behavior, read [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md).

## Recommended workflow

1. Define the learning goal in one sentence.
2. Keep `task_text` as short as possible.
3. Put constraints, examples, and edge cases into `description`.
4. Put didactic hints and structure help into `stoff`.
5. Choose the smallest robust test strategy.
6. Use folder structure only when extra files are genuinely part of the task.

## Field conventions

### `title`

- Short label for lists and navigation.
- Name the concrete task, not the whole lesson.

### `task_text`

- Main student-facing instruction.
- Keep it short and direct.
- Put only the core action here.

Good pattern:
- "Importiere die Liste `temperaturen` und berechne Durchschnitt, Minimum, Maximum und die Anzahl der Werte zwischen 15.0 und 20.0."

### `description`

- Detailed explanation of requirements.
- Use for constraints, variable names, rounding, allowed structures, and examples.
- Also use it to explain what the automatic checks expect.

### `stoff`

- Learning support and didactic explanation.
- Prefer structured HTML when the UI should display multiple hints clearly.
- Use for method reminders, not for the core assignment text.

### `question_text`

- Treat as legacy.
- For new tasks, do not rely on it as the primary student instruction.

## Validation strategy

Choose the simplest test type that makes the task robust.

### Prefer `variable` tests for calculations

Use `variable` tests when students compute named results.

Typical pattern:
- inject fixed input values
- run student code
- compare named variables

This is usually more stable than checking exact console output.

### Use `code_check` for structural requirements

Use `code_check` only for explicit method requirements such as:
- `for`
- `if`
- `import`

Do not use it as the main correctness mechanism when actual values can be checked directly.

### Use `output` only when printed text is the real goal

If the exercise is about formatting console output, `output` is appropriate.
If the goal is a calculation, prefer variable-based checks.

### Use advanced types only deliberately

- `intelligent` for controlled randomization or input-driven checks
- `code_reading` for trace/prediction tasks
- `code_random_complex` for randomized trace tasks

If a normal code task can be validated with fixed variables, that is usually better.

### Critical import rule for `code_random_complex`

For `code_random_complex`, import into another system will fail with HTTP 400 if `code_template`
does not contain either:
- a `values` dict reference, or
- placeholder syntax like `{start}`, `{goal}`, `{board_lines}`.

Authoring safety rules:
- Do not replace technical placeholders in `code_template` with prose-only text.
- Redaction changes should go into `description` and `task_text`, not by removing placeholders.
- Keep `code_template`, `solution_code`, and `randomizer_code` consistent whenever you edit one of them.
- Before export/import, quickly check `code_template` still contains `values` or at least one `{placeholder}` token.

## Folder-based tasks

Use `folderstructure = 1` only when the task really needs extra files.

Typical examples:
- import from a provided Python file
- HTML/CSS/Python multi-file tasks
- `code_ui` tasks

### Current storage model

Folder tasks use split storage:

1. `init.py`
- virtual file from the database
- student-specific content in `user_tasks.current_code`

2. additional files
- stored in `storage/tasks/folders/task_<id>/`
- student overrides may live in `user_task_files`

3. policies
- `.file-policies.json` controls read-only files

### Runtime implication

Extra files are not enough on disk alone.
They must be synchronized into the Pyodide filesystem before RUN, CHECK, and SUBMIT.

That matters for imports like:

```python
from temperaturen import temperaturen
```

## Authoring patterns

### Simple code task

Use when:
- one Python file is enough
- no imports from provided files are needed

Recommended:
- short `task_text`
- detailed `description`
- `variable` or `output` tests

### Calculation task with method requirement

Use when:
- result values matter
- a specific construct must also appear

Recommended:
- `variable` tests for correctness
- `code_check` for required keywords

### Folder import task

Use when:
- students import from a provided helper or data file

Recommended:
- folder structure enabled
- provided file marked read-only if students should not edit it
- tests still target result variables, not just import success

## Writing guidance

- Keep `task_text` minimal.
- Put side conditions into `description`.
- Name required variables explicitly.
- State rounding rules explicitly.
- Avoid mixing pedagogy, grading notes, and the core prompt in one paragraph.

## JSON examples

The following examples are compact templates for common authoring cases.

### A) Calculation task with `variable` tests

```json
{
	"version": "3.0",
	"task_type": "code",
	"title": "Rechteckflaeche berechnen",
	"task_text": "Berechne aus breite und hoehe die Variable flaeche.",
	"description": "Setze die Variable flaeche auf breite * hoehe.",
	"problem_type": "code_completion",
	"code_template": "breite = 4\nhoehe = 7\n# Berechne hier\nflaeche =",
	"solution_code": "flaeche = breite * hoehe",
	"test_cases": [
		{
			"type": "variable",
			"init_vars": { "breite": 4, "hoehe": 7 },
			"expected_vars": { "flaeche": 28 }
		},
		{
			"type": "variable",
			"init_vars": { "breite": 11, "hoehe": 3 },
			"expected_vars": { "flaeche": 33 }
		}
	]
}
```

### B) Calculation + method requirement (`code_check`)

```json
{
	"version": "3.0",
	"task_type": "code",
	"title": "Zahlen im Bereich zaehlen",
	"task_text": "Zaehle, wie viele Werte zwischen 10 und 20 liegen, inklusive Grenzen.",
	"description": "Speichere das Ergebnis in anzahl_im_bereich. Verwende eine Schleife und eine if-Abfrage.",
	"problem_type": "code_completion",
	"code_template": "werte = [8, 10, 13, 21, 20]\nanzahl_im_bereich = 0\nfor wert in werte:\n    pass",
	"solution_code": "anzahl_im_bereich = 0\nfor wert in werte:\n    if 10 <= wert <= 20:\n        anzahl_im_bereich += 1",
	"test_cases": [
		{
			"type": "variable",
			"init_vars": { "werte": [8, 10, 13, 21, 20] },
			"expected_vars": { "anzahl_im_bereich": 3 }
		},
		{
			"type": "code_check",
			"keywords": ["for", "if"],
			"operator": "AND",
			"feedback": "Nutze mindestens eine for-Schleife und eine if-Abfrage."
		}
	]
}
```

### C) Folder task with import from helper file

```json
{
	"version": "3.0",
	"task_type": "code",
	"title": "Temperaturdaten auswerten",
	"task_text": "Importiere temperaturen und berechne durchschnitt, minimum und maximum.",
	"description": "Nutze from temperaturen import temperaturen und berechne die geforderten Variablen.",
	"problem_type": "code_completion",
	"folderstructure": 1,
	"code_template": "from temperaturen import temperaturen\n\nsumme = 0.0\nminimum = temperaturen[0]\nmaximum = temperaturen[0]\nfor wert in temperaturen:\n    pass\ndurchschnitt = 0.0",
	"solution_code": "from temperaturen import temperaturen\n\nsumme = 0.0\nminimum = temperaturen[0]\nmaximum = temperaturen[0]\nfor wert in temperaturen:\n    summe += wert\n    if wert < minimum:\n        minimum = wert\n    if wert > maximum:\n        maximum = wert\ndurchschnitt = round(summe / len(temperaturen), 1)",
	"test_cases": [
		{
			"type": "variable",
			"init_vars": { "temperaturen": [15.0, 20.0, 10.0] },
			"expected_vars": { "minimum": 10.0, "maximum": 20.0, "durchschnitt": 15.0 }
		},
		{
			"type": "code_check",
			"keywords": ["import", "for", "if"],
			"operator": "AND"
		}
	]
}
```

Note for example C:
- The JSON defines the task record.
- The imported file `temperaturen.py` must also exist in the task folder.
- In runtime, folder files must be synchronized to Pyodide before imports work.

## Related docs

- [CONTEXT_CURRENT.md](CONTEXT_CURRENT.md)
- [taskexport.md](taskexport.md)
- [test-types-documentation-final-v2.md](test-types-documentation-final-v2.md)
- [code-ui.md](code-ui.md)
- [code-ui-architecture.md](code-ui-architecture.md)