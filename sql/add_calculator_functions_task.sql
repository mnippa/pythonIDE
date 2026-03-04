-- Add Taschenrechner mit Funktionen Task to Assignment #21
-- Task teaches function definition and usage with a calculator UI

-- Get database connection
USE python_ide;

-- Get the next position for assignment #21
SET @next_position = (SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE assignment_id = 21);

INSERT INTO tasks (
    assignment_id,
    title,
    position,
    type,
    task_type,
    description,
    initial_code,
    solution_code,
    test_cases,
    validation_mode,
    points,
    time_limit,
    difficulty,
    tags,
    hints,
    learning_objectives,
    folder_path
) VALUES (
    21,                                                     -- assignment_id
    'Taschenrechner mit Funktionen',                       -- title
    @next_position,                                        -- position
    'code-ui',                                             -- type
    'code-ui',                                             -- task_type
    
    -- description (Markdown)
    '# Taschenrechner mit Funktionen

## Aufgabe
Erstelle einen Taschenrechner, der **vier Funktionen** verwendet um Berechnungen durchzuführen.

## Anforderungen

### 1. Funktionen definieren
Implementiere die folgenden vier Funktionen:

- `plus(a, b)` - Addiert zwei Zahlen
- `minus(a, b)` - Subtrahiert b von a
- `mal(a, b)` - Multipliziert zwei Zahlen
- `geteilt(a, b)` - Dividiert a durch b

Jede Funktion soll:
- Zwei Parameter `a` und `b` annehmen
- Das Ergebnis mit `return` zurückgeben

### 2. Funktionen verwenden
Das Hauptprogramm soll:
- Die Eingabewerte `a` und `b` einlesen
- Prüfen, welcher Button gedrückt wurde
- Die entsprechende Funktion aufrufen
- Das Ergebnis im UI anzeigen

### 3. Fehlerbehandlung
- Division durch 0 abfangen und Fehlermeldung ausgeben
- Ungültige Eingaben (keine Zahlen) behandeln

## Beispiel

```python
def plus(a, b):
    return a + b

# Funktion verwenden:
ergebnis = plus(10, 5)
ui.set("result", ergebnis)  # Zeigt: 15
```

## Tipps
- Jede Funktion sollte nur **eine Zeile** Code enthalten: `return a <operator> b`
- Bei Division: Prüfe `if b == 0:` vor der Berechnung
- Verwende `ui.get("__trigger__")` um zu erkennen, welcher Button gedrückt wurde
- Die `return`-Anweisung gibt einen Wert an den Aufrufer zurück

## Lernziele
- Funktionen mit Parametern und Rückgabewerten definieren
- Funktionen aus dem Hauptprogramm aufrufen
- **DRY-Prinzip**: "Don\'t Repeat Yourself" - Code-Wiederverwendung durch Funktionen
- Bedingungen verwenden um zwischen Operationen zu unterscheiden',

    -- initial_code (Starter code from main.py)
    'import idegui as ui

# Definiere hier die vier Funktionen:

def plus(a, b):
    """Addiert zwei Zahlen und gibt das Ergebnis zurück"""
    # TODO: Implementiere die Addition
    pass

def minus(a, b):
    """Subtrahiert b von a und gibt das Ergebnis zurück"""
    # TODO: Implementiere die Subtraktion
    pass

def mal(a, b):
    """Multipliziert zwei Zahlen und gibt das Ergebnis zurück"""
    # TODO: Implementiere die Multiplikation
    pass

def geteilt(a, b):
    """Dividiert a durch b und gibt das Ergebnis zurück"""
    # TODO: Implementiere die Division
    pass


# Hauptprogramm
try:
    # Lies die Eingabewerte
    a = float(ui.get("a"))
    b = float(ui.get("b"))
    
    # Ermittle welcher Button gedrückt wurde
    operation = ui.get("__trigger__")
    
    # Rufe die passende Funktion auf und zeige das Ergebnis
    if operation == "plus":
        # TODO: Rufe die plus() Funktion auf und speichere das Ergebnis
        pass
    elif operation == "minus":
        # TODO: Rufe die minus() Funktion auf und speichere das Ergebnis
        pass
    elif operation == "mal":
        # TODO: Rufe die mal() Funktion auf und speichere das Ergebnis
        pass
    elif operation == "geteilt":
        # TODO: Rufe die geteilt() Funktion auf und speichere das Ergebnis
        # Beachte: Division durch 0 behandeln!
        pass
    
except ValueError:
    ui.set("result", "Fehler: Bitte Zahlen eingeben")
except Exception as e:
    ui.set("result", f"Fehler: {e}")
',

    -- solution_code (Complete working solution)
    'import idegui as ui

def plus(a, b):
    """Addiert zwei Zahlen und gibt das Ergebnis zurück"""
    return a + b

def minus(a, b):
    """Subtrahiert b von a und gibt das Ergebnis zurück"""
    return a - b

def mal(a, b):
    """Multipliziert zwei Zahlen und gibt das Ergebnis zurück"""
    return a * b

def geteilt(a, b):
    """Dividiert a durch b und gibt das Ergebnis zurück"""
    if b == 0:
        return "Fehler: Division durch 0 nicht möglich"
    return a / b


# Hauptprogramm
try:
    a = float(ui.get("a"))
    b = float(ui.get("b"))
    
    operation = ui.get("__trigger__")
    
    if operation == "plus":
        ergebnis = plus(a, b)
        ui.set("result", ergebnis)
    elif operation == "minus":
        ergebnis = minus(a, b)
        ui.set("result", ergebnis)
    elif operation == "mal":
        ergebnis = mal(a, b)
        ui.set("result", ergebnis)
    elif operation == "geteilt":
        ergebnis = geteilt(a, b)
        ui.set("result", ergebnis)
    
except ValueError:
    ui.set("result", "Fehler: Bitte Zahlen eingeben")
except Exception as e:
    ui.set("result", f"Fehler: {e}")
',

    -- test_cases (JSON - currently NULL for code-ui tasks)
    NULL,
    
    'none',                                                -- validation_mode
    10,                                                    -- points
    600,                                                   -- time_limit (10 minutes)
    'beginner',                                            -- difficulty
    
    -- tags (JSON array)
    '["funktionen", "parameter", "return", "code-ui", "calculator", "grundlagen"]',
    
    -- hints (JSON array)
    '[
        "Jede Rechenoperation braucht nur eine Zeile: `return a + b`",
        "Bei der Division musst du prüfen ob b gleich 0 ist",
        "Verwende die Funktionen so: `ergebnis = plus(a, b)`",
        "Das Ergebnis speicherst du dann mit `ui.set(\\"result\\", ergebnis)`"
    ]',
    
    -- learning_objectives (JSON array)
    '[
        "Funktionen mit Parametern definieren",
        "Return-Anweisungen verwenden",
        "Funktionen aufrufen und Rückgabewerte nutzen",
        "Code-Wiederverwendung durch Funktionen",
        "Division durch Null behandeln"
    ]',
    
    'task_170'                                             -- folder_path
);

-- Get the ID of the newly inserted task
SET @task_id = LAST_INSERT_ID();

-- Output success message
SELECT 
    @task_id as 'Task ID',
    'Task successfully added to Assignment #21' as 'Status';

-- Show all tasks in assignment #21 for verification
SELECT 
    t.id,
    t.position,
    t.title,
    t.type,
    t.difficulty,
    t.points
FROM tasks t
WHERE assignment_id = 21
ORDER BY position;
