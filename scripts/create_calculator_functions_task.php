<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Executing SQL script...\n\n";
    
    $sql = file_get_contents(__DIR__ . '/../sql/add_calculator_functions_task.sql');
    
    // Split by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   $stmt !== 'USE python_ide';
        }
    );
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        try {
            $result = $pdo->query($statement);
            if ($result && $result->rowCount() > 0) {
                echo "Statement executed successfully\n";
            }
        } catch (PDOException $e) {
            // Skip SET statements errors
            if (strpos($statement, 'SET @') === false) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Verification ===\n";
    $stmt = $pdo->query("SELECT id, title, position FROM tasks WHERE id = 170");
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo "✓ Task 170 created successfully!\n";
        echo "  Title: " . $task['title'] . "\n";
        echo "  Position: " . $task['position'] . "\n";
    } else {
        echo "✗ Task 170 not found. Creating manually...\n\n";
        
        // Create the task manually
        $insertSQL = "INSERT INTO tasks (
            assignment_id, title, position, type, task_type, description,
            initial_code, solution_code, test_cases, validation_mode,
            points, time_limit, difficulty, tags, hints, learning_objectives, folder_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($insertSQL);
        
        $next_position = $pdo->query("SELECT COALESCE(MAX(position), 0) + 1 as p FROM tasks WHERE assignment_id = 21")->fetch()['p'];
        
        $description = '# Taschenrechner mit Funktionen

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
- Bedingungen verwenden um zwischen Operationen zu unterscheiden';

        $initial_code = file_get_contents(__DIR__ . '/../storage/tasks/folders/task_170/main.py');
        
        $solution_code = 'import idegui as ui

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
    ui.set("result", f"Fehler: {e}")';
        
        $tags = '["funktionen", "parameter", "return", "code-ui", "calculator", "grundlagen"]';
        $hints = '[
            "Jede Rechenoperation braucht nur eine Zeile: `return a + b`",
            "Bei der Division musst du prüfen ob b gleich 0 ist",
            "Verwende die Funktionen so: `ergebnis = plus(a, b)`",
            "Das Ergebnis speicherst du dann mit `ui.set(\\"result\\", ergebnis)`"
        ]';
        $learning_objectives = '[
            "Funktionen mit Parametern definieren",
            "Return-Anweisungen verwenden",
            "Funktionen aufrufen und Rückgabewerte nutzen",
            "Code-Wiederverwendung durch Funktionen",
            "Division durch Null behandeln"
        ]';
        
        $stmt->execute([
            21,  // assignment_id
            'Taschenrechner mit Funktionen',  // title
            $next_position,  // position
            'code-ui',  // type
            'code-ui',  // task_type
            $description,
            $initial_code,
            $solution_code,
            NULL,  // test_cases
            'none',  // validation_mode
            10,  // points
            600,  // time_limit
            'beginner',  // difficulty
            $tags,
            $hints,
            $learning_objectives,
            'task_170'  // folder_path
        ]);
        
        echo "✓ Task 170 created manually!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
