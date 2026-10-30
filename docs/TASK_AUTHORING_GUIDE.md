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

Task positions are consecutive within each assignment, starting at 1. Moving a
task inserts it at the requested position and shifts its neighbours; it is not
a two-request swap. Task locks protect content, while ordering requires only
an unlocked assignment. Creation, deletion, imports and clones normalize the
affected task list in the same transaction. Equal legacy positions are ordered
by task ID before normalization. Direct authoring scripts must likewise use
`components/task_order.php` inside a transaction with the assignment row locked.

## Clipboard in restricted editors

The existing restricted editor mode uses a page-memory clipboard rather than
the system clipboard. Copy, cut and paste work with Ctrl/Cmd+C/X/V, Ctrl+Insert,
Shift+Insert, Shift+Delete and a dedicated right-click menu. With no selection,
copy/cut operates on the current line. Multiple selections and Undo/Redo are
supported. Native paste and text drops cannot import external clipboard data.
The internal buffer survives focus changes but is cleared on editor disposal
or page reload. Admin test mode and the personal projects page keep their
existing unrestricted clipboard behavior. This is a UI restriction, not a
tamper-proof exam environment.

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
- Keep only necessary result structure, output format, and meaningful edge cases;
  omit solution recipes and repeated test-runtime details.
- AutoDescr combines identical rows from fixed and intelligent tests. Its
  Test-Anforderungen table is stored in the description; the editor does not
  append a separate validation summary.

### UI wording rule (Title vs Details)

- Keep `title` short and precise (navigation label).
- Put technical implementation guidance into `description` (shown as details in admin/student views).
- Keep `task_text` as the concise assignment action, not as the technical checklist.

Recommended split:
- `title`: what the task is in one short phrase.
- `task_text`: what the student should do now (one core action).
- `description` (Details): technical specifics, required attributes, trigger names, variable names, and constraints.

### `stoff`

- Learning support and didactic explanation.
- Prefer structured HTML when the UI should display multiple hints clearly.
- Use for method reminders, not for the core assignment text.

### `question_text`

- Treat as legacy.
- For new tasks, do not rely on it as the primary student instruction.

## DB- und UML-Modellierungsaufgaben

Bei Aufgaben wie „UML-Klassendiagramm in ein relationales Schema umsetzen“ ist
die fachliche Modellierung das Lernziel, nicht das Abschreiben von Namen.

- Formuliere den fachlichen Sachverhalt und den Arbeitsauftrag, aber blende
  keine Tabellen-/Feldnamen der Musterlösung als Pflichtvorgabe ein.
- Bewerte Tabellen/Klassen und Attribute/Felder nicht standardmäßig durch
  exakten Zeichenkettenvergleich. Unterschiedliche, fachlich passende Namen
  müssen grundsätzlich möglich sein.
- Hinterlege die Referenzlösung als Rubrik mit stabilen internen Rollen, z. B.
  „Bestellung“, „gehört zu Kunde“ oder „Positionsmenge“. Trenne diese Rollen
  von den sichtbaren Namen in Aufgabenstellung und studentischem Modell.
- Lege explizit fest, welche Eigenschaften fachlich entscheidend sind:
  Elemente, Datentypen, Primär-/Fremdschlüssel, Beziehungen, Kardinalitäten
  sowie berechnete oder gespeicherte Werte.
- Hinterlege tolerierte Alternativen dort, wo es mehrere fachlich vertretbare
  Lösungen gibt. Dazu gehören zum Beispiel Datentypfamilien oder die Frage,
  ob ein abgeleiteter Wert gespeichert wird.
- Bei nicht eindeutig zuordenbaren Studentenelementen soll die Rubrik eine
  manuelle Prüfung erlauben, statt eine plausible Lösung automatisch als
  falsch zu bewerten.
- Formuliere Checks als Lernfeedback: benenne das betroffene Element aus dem
  studentischen Modell und erkläre die verletzte Regel. Bei einer falschen
  Kardinalität soll beispielsweise erklärt werden, welche Seite der Beziehung
  genau ein oder mehrere Elemente zulässt. Verrate dabei keine Soll-Namen oder
  Referenzstruktur.
- Trenne formative Einzelchecks von der Abschlussbewertung. Ein einzelner
  Check soll nicht automatisch als endgültiges Bestehen oder Durchfallen
  gelten, sofern die Aufgabe das nicht ausdrücklich so festlegt.

## Validation strategy

### Dictionary basics before loops

Keep the input shape fixed when an exercise is intended to be solved by manual
index/key access. Vary values, not the number of records. Reference solutions
must not require loops or comprehensions. For construction from a fixed business
description, specify the result variable, then compare the resulting dictionary
rather than its source-code spelling or printed representation. Dictionary key
order must not matter.

When designing the structure is the learning goal, do not reveal the schema,
key names or suitable types in the description. Ask for an independent first
draft. Put the exact checking contract in optional `hint1`/`hint2`/`hint3` fields
and explicitly ask students to reconcile their draft with it before checking.
Be transparent that fixed-schema checks do not accept alternative key names.
This exception overrides the general description-field advice above. Access-only
exercises may expose their already-given input structure.

The current four-task block in live Assignment 48 is defined in
[`revise_assignment48_dictionary_basics.php`](../scripts/revise_assignment48_dictionary_basics.php):
tasks 569/570 replace the former loop-based tasks; 571/572 add nested dictionaries
and dictionary/list access. The earlier creation script is historical and must
not be used to recreate the superseded tasks. Run
`node scripts/test_dictionary_basics.js --browser-bundle <session-file>` to
generate tests for the existing browser Python runtime.

For intelligent tests in `vars` mode, set `"value_comparison": "structural"`
when dictionary key order must not affect correctness. This opts into the
existing recursive value comparison: list order matters, dictionary key order
does not. Tests without this setting keep their previous comparison.

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

### Opt-in iteration context for exact randomized quotas

Only new tasks that set `"iteration_context": true` on the first entry of
`variable_overrides` receive `__previous_inputs` in their randomizer namespace.
This list contains the input dictionaries of earlier iterations. Choose from
the remaining quota to enforce exact counts in a randomized order.

Tasks without this explicit boolean flag retain the original empty randomizer
namespace. Missing history for opted-in tasks is an error, not a reason to
silently generate a different distribution. All generated inputs continue to be
persisted in `user_tasks.iteration_values` using the existing mechanism.
Deploy the updated quiz renderer before enabling the flag on a live task.

### Two 8-bit answers separated by a semicolon

New `code_random_complex` tasks can set `"answer_format": "binary_pair_8"`
on the first `variable_overrides` entry. Both quiz submission endpoints then
ignore whitespace, including inside the bit strings, but require exactly
`xxxxxxxx;xxxxxxxx` with eight binary digits per value. Leading zeros remain
mandatory. Tasks without this flag retain their existing answer comparison.
Deploy both endpoints and `components/quiz_binary_pair.php` before enabling it.

For a combined decimal-to-binary subtraction exercise, use
`"answer_format": "binary_subtraction_steps_8"` instead. It requires five
semicolon-separated fields: first number (8 bits), second number (8 bits),
second number's two's complement (8 bits), difference (8 bits), and the positive
decimal difference. Whitespace is ignored; all four binary fields require
leading zeros. This format is independent of the existing two-field format.

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

- Use real German umlauts and ß in student-facing text; retain ae/oe/ue/ss in variable names.
- Attempt limits apply to the entire task across all iterations. Iterative quiz tasks count wrong answers, not successful iterations.
- When exact randomized category counts are required, sample from the remaining quota rather than choosing categories independently.
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