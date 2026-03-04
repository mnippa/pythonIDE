<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Creating Task 170: Taschenrechner mit Funktionen...\n\n";
    
    // Get next position
    $stmt = $pdo->query("SELECT COALESCE(MAX(position), 0) + 1 as p FROM tasks WHERE assignment_id = 21");
    $next_position = $stmt->fetch()['p'];
    
    echo "Next position: $next_position\n";
    
    $description = '<div class="task-details">
  <h4>Aufgabenbeschreibung</h4>
  <p>Erstelle einen <strong>Taschenrechner</strong>, der vier <strong>Funktionen</strong> verwendet, um Berechnungen durchzuführen.</p>
  
  <h5>Anforderungen:</h5>
  <ol>
    <li><strong>Funktionen definieren:</strong> Implementiere <code>plus(a, b)</code>, <code>minus(a, b)</code>, <code>mal(a, b)</code> und <code>geteilt(a, b)</code></li>
    <li><strong>Return-Werte:</strong> Jede Funktion gibt ihr Ergebnis mit <code>return</code> zurück</li>
    <li><strong>Funktionen aufrufen:</strong> Verwende die Funktionen basierend auf dem gedrückten Button</li>
    <li><strong>Fehlerbehandlung:</strong> Division durch 0 abfangen</li>
  </ol>
  
  <h5>Lernziele:</h5>
  <ul>
    <li>Funktionen mit Parametern und Rückgabewerten definieren</li>
    <li>Code-Wiederverwendung durch Funktionen (DRY-Prinzip)</li>
    <li>Return-Anweisungen korrekt verwenden</li>
  </ul>
</div>';

    $task_text = 'Baue einen Taschenrechner mit vier Funktionen (plus, minus, mal, geteilt), die jeweils zwei Zahlen verarbeiten und das Ergebnis zurückgeben. Das Hauptprogramm ruft die richtige Funktion basierend auf dem gedrückten Button auf.';
    
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

    $stoff = '<div class="stoff-block">
  <h4>Stoff: Funktionen mit Parametern und Return-Werten</h4>
  <ul>
    <li><strong>Funktion definieren:</strong> <code>def funktionsname(param1, param2):</code></li>
    <li><strong>Return-Anweisung:</strong> <code>return ergebnis</code> gibt einen Wert an den Aufrufer zurück</li>
    <li><strong>Funktion aufrufen:</strong> <code>resultat = funktionsname(10, 5)</code> speichert den Rückgabewert</li>
    <li><strong>DRY-Prinzip:</strong> "Don\'t Repeat Yourself" - Wiederholten Code in Funktionen auslagern</li>
    <li><strong>Fehlerbehandlung:</strong> Bedingungen innerhalb der Funktion prüfen (z.B. Division durch 0)</li>
  </ul>
</div>';
    
    $sql = "INSERT INTO tasks (
        assignment_id,
        title,
        description,
        task_text,
        position,
        task_type,
        problem_type,
        folderstructure,
        allowDownload,
        allow_code_ui_web_edit,
        code_template,
        solution_code,
        hint1,
        hint2,
        hint3,
        stoff,
        max_attempts,
        iterations_count,
        show_solution,
        show_solution_code,
        created_at,
        updated_at
    ) VALUES (
        :assignment_id,
        :title,
        :description,
        :task_text,
        :position,
        :task_type,
        :problem_type,
        :folderstructure,
        :allowDownload,
        :allow_code_ui_web_edit,
        :code_template,
        :solution_code,
        :hint1,
        :hint2,
        :hint3,
        :stoff,
        :max_attempts,
        :iterations_count,
        :show_solution,
        :show_solution_code,
        NOW(),
        NOW()
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':assignment_id' => 21,
        ':title' => 'Taschenrechner mit Funktionen',
        ':description' => $description,
        ':task_text' => $task_text,
        ':position' => $next_position,
        ':task_type' => 'code_ui',
        ':problem_type' => 'code_completion',
        ':folderstructure' => 1,
        ':allowDownload' => 0,
        ':allow_code_ui_web_edit' => 0,
        ':code_template' => $initial_code,
        ':solution_code' => $solution_code,
        ':hint1' => 'Jede Rechenoperation braucht nur eine Zeile in der Funktion: `return a + b`',
        ':hint2' => 'Bei der Division musst du prüfen ob b gleich 0 ist: `if b == 0: return "Fehler"`',
        ':hint3' => 'Rufe die Funktionen so auf: `ergebnis = plus(a, b)` und dann `ui.set("result", ergebnis)`',
        ':stoff' => $stoff,
        ':max_attempts' => 10,
        ':iterations_count' => 1,
        ':show_solution' => 1,
        ':show_solution_code' => 1
    ]);
    
    $task_id = $pdo->lastInsertId();
    
    echo "✓ Task created successfully!\n";
    echo "  Task ID: $task_id\n";
    echo "  Title: Taschenrechner mit Funktionen\n";
    echo "  Position: $next_position\n";
    echo "  Assignment: #21\n\n";
    
    echo "=== Verifying task in database ===\n";
    $stmt = $pdo->prepare("SELECT id, title, task_type, position FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo "✓ Task verified in database:\n";
        print_r($task);
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
