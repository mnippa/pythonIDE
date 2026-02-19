# Task-Typen Dokumentation

Eine vollständige Referenz aller Aufgabentypen im Python IDE System. Diese Dokumentation beschreibt Struktur, Felder und wie man Aufgaben direkt via SQL erstellen kann.

## Übersicht der Task-Typen

| Typ | Beschreibung | Eingabe-Element | Test-Methode |
|-----|--------------|-----------------|--------------|
| `single_choice` | Einfachwahlaufgabe | Radio Button | Option-Hervorhebung |
| `multiple_choice` | Mehrfachwahlaufgabe | Checkboxes | Option-Hervorhebung |
| `free_text` | Freitextantwort | Textarea | Keyword-Matching |
| `code` | Code mit Test-Cases | Code-Editor | Python-Test-Ausführung |
| `code_reading` | Code-Analyse/Vorhersage | Textfeld | Python-Code-Auswertung |
| `code_random_complex` | Code mit zufälligen Werten | Textfeld | Template-Auswertung |

---

## 1. Single Choice (Einfachwahlaufgabe)

**Beschreibung:** Benutzer wählt EINE korrekte Antwort aus mehreren Optionen.

**Datenbank-Tabellen:**
- `tasks`: Hauptaufgabe
- `task_options`: Antwortoptionen

### Wichtige Felder in `tasks`

```sql
task_type = 'single_choice'
question_text = 'Die Frage...'
description = 'Optionale Aufgabenbeschreibung'
max_attempts = 1
hint1, hint2, hint3 = 'Hinweistexte'
stoff = 'Lernmaterial'
```

### SQL-Beispiel

```sql
INSERT INTO tasks (assignment_id, title, task_type, question_text, description, max_attempts)
VALUES (1, 'Operators richtig', 'single_choice', 'Was ist der Ausgabewert?', 'Analysiere', 1);

INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES
(LAST_INSERT_ID(), 'Richtig', TRUE, 1),
(LAST_INSERT_ID(), 'Falsch 1', FALSE, 2),
(LAST_INSERT_ID(), 'Falsch 2', FALSE, 3);
```

---

## 2. Multiple Choice (Mehrfachwahlaufgabe)

**Beschreibung:** Benutzer wählt MEHRERE korrekte Antworten aus. Alle müssen richtig gewählt werden.

### SQL-Beispiel

```sql
INSERT INTO tasks (assignment_id, title, task_type, question_text, max_attempts)
VALUES (1, 'Mehrfache Antworten', 'multiple_choice', 'Welche sind richtig?', 2);

INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES
(LAST_INSERT_ID(), 'Richtig 1', TRUE, 1),
(LAST_INSERT_ID(), 'Falsch', FALSE, 2),
(LAST_INSERT_ID(), 'Richtig 2', TRUE, 3);
```

---

## 3. Free Text (Freitextantwort)

**Beschreibung:** Benutzer gibt einen Freitext ein, der gegen Schlüsselwörter geprüft wird.

### Wichtige Felder in `tasks`

```sql
task_type = 'free_text'
question_text = 'Die Frage...'
correct_answer = 'Schlüsselwort1'
description = 'Erklärung'
max_attempts = 3
validation_mode = 'loose'
```

### SQL-Beispiel

```sql
INSERT INTO tasks (assignment_id, title, task_type, question_text, correct_answer, validation_mode, max_attempts)
VALUES (1, 'Definition', 'free_text', 'Erkläre was eine Variable ist', 'speichert', 'loose', 3);
```

---

## 4. Code (Code mit Test-Cases)

**Beschreibung:** Benutzer schreibt Code, der gegen vordefinierte Test-Cases geprüft wird.

### Wichtige Felder in `tasks`

```sql
task_type = 'code'
question_text = 'Aufgabenstellung...'
code_template = 'def hello():\n    pass'
description = 'Detaillierte Erklärung'
test_cases = '[{"name":"Test 1","code":"assert hello() == 42","function_name":"hello"}]'
validation_mode = 'strict'
max_attempts = 10
```

### SQL-Beispiel

```sql
INSERT INTO tasks (assignment_id, title, task_type, question_text, code_template, 
    test_cases, validation_mode, max_attempts)
VALUES (1, 'Funktionen schreiben', 'code', 'Schreibe sum_list()',
    'def sum_list(lst):\n    pass',
    '[{"name":"Test 1","code":"assert sum_list([1,2,3]) == 6","function_name":"sum_list"}]',
    'strict', 10);
```

---

## 5. Code Reading (Code-Analyse)

**Beschreibung:** Benutzer analysiert vorgefertigten Code und gibt das Ergebnis ein. Verwendet **FESTE Variablen-Wert-Paare** in `variable_overrides` - das System wählt bei jedem Laden eine zufällige Kombination aus den vordefinierten Werten.

### Wichtige Felder in `tasks`

```sql
task_type = 'code_reading'
question_text = 'Code-Analysierung...'
code_template = 'Code mit Platzhaltern {variable}'
correct_answer = 'result'
description = 'Instruktionen'
variable_overrides = '{"x": [1, 2, 3], "y": [10, 20, 30]}'  -- FESTE Werte!
validation_mode = 'loose'
max_attempts = 3
```

### Variable Overrides Formate

**Format 1: Objekt mit Arrays**
```json
{
    "x": [1, 2, 3],
    "y": [10, 20, 30],
    "operation": ["add", "multiply"]
}
```
→ System wählt random aus jeder Array einen Wert

**Format 2: Array von Objekten**
```json
[
    {"start": 1, "end": 5},
    {"start": 1, "end": 10},
    {"start": 5, "end": 9}
]
```
→ System wählt random ein Objekt aus dem Array

### SQL-Beispiel

```sql
-- Format 1: Einzelne Arrays
INSERT INTO tasks (assignment_id, title, task_type, question_text, code_template, 
    correct_answer, variable_overrides, validation_mode)
VALUES (1, 'Listen analysieren', 'code_reading',
    'Was ist das Ergebnis?',
    'nums = [x, y, x + y]\nresult = sum(nums)',
    'result',
    '{"x":[1,2,3],"y":[4,5,6]}',
    'loose');

-- Format 2: Array von Objekten
INSERT INTO tasks (assignment_id, title, task_type, question_text, code_template,
    correct_answer, variable_overrides, validation_mode)
VALUES (1, 'Schleife', 'code_reading',
    'Welche Summe?',
    '# Berechne Summe von {start} bis {end}\nresult = 0\nfor i in range({start}, {end} + 1):\n    result += i',
    'result',
    '[{"start":1,"end":5},{"start":1,"end":10}]',
    'loose');
```

---

## 6. Code Random Complex (Code mit dynamischen Zufallswerten)

**Beschreibung:** Code mit **individueller Zufallsfunktion** in `code_template`, die bei jedem Laden neue Werte generiert. Student muss das Ergebnis vorhersagen.

### Wichtige Felder in `tasks`

```sql
task_type = 'code_random_complex'
question_text = 'Aufgabenstellung...'
code_template = 'Generator-Code, der values-Dict fuellt'   -- ✓ ZUFALLSFUNKTION (PFLICHT)
solution_code = 'Berechnung mit values[...]'               -- Ergebnis in result
correct_answer = 'result'                                  -- Var-Name fuer Ergebnis
description = 'Lernmaterial'
variable_overrides = NULL                                  -- Nicht erlaubt
validation_mode = 'strict'
max_attempts = 5
```

### ✅ EMPFOHLENER Ansatz: Zufallsfunktion in code_template

Verwende **code_template** mit einer Zufallsfunktion und schreibe die Werte in ein `values`-Dict.

**Vorteile:**
- ✅ Echte Zufallswerte bei jedem Laden
- ✅ Flexible Wertebereiche (z.B. 0-255)
- ✅ Individuelle Logik pro Aufgabe
- ✅ Natürliches Python (random.randint, random.choice, etc.)

```sql
code_template = 'import random\nnum = random.randint(0, 255)\nvalues = {"num": num}'
solution_code = 'binary = format(values["num"], "08b")\nresult = int(binary, 2)'
correct_answer = 'result'
variable_overrides = NULL
```

### Generator-Workflow (kurz)

1. `code_template` fuehrt Python aus und setzt **values** (dict).
2. UI zeigt Werte aus **values** im Quiz an.
3. `solution_code` wird mit **values** evaluiert und `result` wird mit der Eingabe verglichen.

### Unterstuetzte Datentypen fuer `values`

Alle **JSON-serialisierbaren** Typen sind erlaubt:
- `string`, `number`, `boolean`
- `array` (Liste)
- `object` (dict)

Hinweis: `values` muss ein **dict** sein. Arrays oder Strings als Root sind nicht erlaubt.

### Feste Wertepaare (nur code_reading)

`variable_overrides` ist **nur fuer code_reading** erlaubt. Fuer `code_random_complex` sind feste Wertepaare nicht zulaessig.

### SQL-Beispiel (mit Zufallsfunktion)

```sql
INSERT INTO tasks (assignment_id, title, task_type, question_text, solution_code,
    correct_answer, code_template, validation_mode, max_attempts, variable_overrides)
VALUES (18, 'Binär zu Dezimal', 'code_random_complex',
    'Was ist der Dezimalwert der Binärzahl?',
    'def binary_to_decimal(binary_str):\n    decimal = 0\n    for i, bit in enumerate(reversed(binary_str)):\n        if bit == "1":\n            decimal += 2 ** i\n    return decimal\nbinary = format({num}, "08b")\nresult = binary_to_decimal(binary)',
    'result',
    'random.randint(0, 255)',  -- Zufallsfunktion!
    'strict',
    5,
    NULL);
```

---

## Best Practices

### 1. JSON-Format korrekt

```sql
-- ✅ RICHTIG: Single quotes außen, escaped quotes innen
variable_overrides = '{"binary":["1010","1101"]}'

-- ❌ FALSCH: Unescaped quotes
variable_overrides = {"binary":["1010","1101"]}
```

### 2. Code-Template mit Escaping

```sql
-- ✅ RICHTIG: Escaped innere Anführungszeichen
code_template = 'binary = format(random.randint(0, 255), \"08b\")'

-- ❌ FALSCH: Unescaped quotes
code_template = 'binary = format(random.randint(0, 255), "08b")'
```

### 3. Platzhalter in code_random_complex

```sql
-- ✅ RICHTIG: {varName} wird ersetzt, Strings escaped
solution_code = 'binary = "{binary}"\nresult = int(binary, 2)'

-- ❌ FALSCH: Keine Quotes um Placeholder
solution_code = 'binary = {binary}\nresult = int(binary, 2)'
---

## Variable Overrides Vergleich

| Feature | variable_overrides (code_reading) | code_template (code_random_complex) |
|---------|-------------------|--------------------------------|
| Kontrolle | Hoch (vordefiniert) | Niedrig (zufaellig) |
| Performance | Schnell | Langsam |
| Vorhersehbarkeit | Hoch | Niedrig |
| Testbarkeit | Einfach | Schwierig |
| **Einsatz** | **Nur code_reading** | **Pflicht fuer code_random_complex** |

---

## Weitere Ressourcen

- **Database Schema:** `sql/schema.sql`
- **API Endpoints:** `/api/tasks/`, `/api/user_tasks/`
- **Frontend Renderer:** `public/js/quiz-renderer.js`
- **Admin Form:** `public/admin.php`
