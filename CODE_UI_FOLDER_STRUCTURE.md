# Code-UI Task Structure Documentation

## 📁 Ordnerstruktur für Code-UI Tasks

Jeder Code-UI Task (mit `task_type='code_ui'` und `folderstructure=1`) **MUSS** folgende Dateien enthalten:

```
storage/tasks/folders/task_XXX/
├── .file-policies.json    ✅ REQUIRED - Definiert read-only Dateien
├── idegui.py              ✅ REQUIRED - API-Dokumentation (read-only)
├── index.html             ✅ REQUIRED - UI/HTML-Struktur
├── style.css              ✅ REQUIRED - CSS-Styling
└── (init.py)              ❌ NICHT im Filesystem! Kommt aus der DB!
```

---

## 🔧 Datei-Details

### 1. `.file-policies.json`

Definiert, welche Dateien editierbar sind und welche read-only.

**Template:**
```json
{
    "files": {
        "idegui.py": {
            "read_only": true
        },
        "index.html": {
            "read_only": false
        },
        "style.css": {
            "read_only": false
        }
    }
}
```

**Best Practice:**
- `idegui.py` **immer** read-only (API-Dokumentation)
- `index.html` & `style.css` je nach Aufgabe read-only oder editierbar
- `init.py` **NICHT** in dieser Datei erwähnen (ist virtuell)

---

### 2. `idegui.py`

API-Dokumentation und Stub-Funktionen für Autocomplete.

**Template:**
```python
"""idegui - Simple UI Bridge for Code-UI Tasks

Injected by JavaScript at runtime. Allows Python to read/write HTML elements.

API:
  ui.get(name, default="")  - Read value from HTML element
  ui.set(name, value)       - Write value to HTML element
  ui.print(container, ...)  - Print text to container (appends)
  ui.reset(container)       - Clear container content
  ui.trigger.name           - Name of clicked trigger
  ui.trigger.value          - Value of clicked trigger

Example:
  a = float(ui.get('a'))
  b = float(ui.get('b'))
  result = a + b
  ui.set('result', result)
  ui.print('log', 'Calculated:', result)

Event-Driven Functions:
  When using data-function="myFunc":
  
  def myFunc(trigger):
      ui.set('output', f"Called by {trigger.name}")
"""

def get(name, default=""):
    return default

def set(name, value):
    return str(value)

def print(container, *args, sep=' ', end='\\n'):
    pass

def reset(container):
    pass

class _Trigger:
    name = ""
    value = ""

trigger = _Trigger()

__all__ = ["get", "set", "print", "reset", "trigger"]
```

**Wichtig:**
- Datei ist **read-only**
- Dient nur zur Dokumentation und IDE-Unterstützung
- Echte Implementation kommt vom JavaScript-Runtime

---

### 3. `index.html`

UI-Struktur mit `data-element` Attributen für Python-Zugriff.

**Template:**
```html
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Task Title</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1>Task Title</h1>
    
    <!-- Input elements with data-element for Python access -->
    <input type="number" data-element="num1" placeholder="Zahl 1">
    <input type="number" data-element="num2" placeholder="Zahl 2">
    
    <!-- Buttons with trigger modes -->
    <!-- TRADITIONAL MODE: Full code restart -->
    <button data-run-python="true" data-run-name="calculate">
      Berechnen (Traditional)
    </button>
    
    <!-- EVENT-DRIVEN MODE: Only function call -->
    <button data-function="calculate">
      Berechnen (Event-Driven)
    </button>
    
    <!-- Output elements -->
    <div data-element="result">Ergebnis: -</div>
    <div data-element="output">Output: -</div>
  </div>
</body>
</html>
```

**Wichtige Attribute:**
- `data-element="name"` - Python kann mit `ui.get('name')` / `ui.set('name', value)` darauf zugreifen
- `data-run-python="true"` + `data-run-name="trigger"` - Traditional Mode (kompletter Code restart)
- `data-function="functionName"` - Event-Driven Mode (nur Funktion aufrufen)

---

### 4. `style.css`

Styling für die UI.

**Template:**
```css
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Segoe UI', Tahoma, sans-serif;
  background: #f5f5f5;
  padding: 20px;
}

.container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

input, button {
  padding: 10px;
  margin: 5px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

button {
  background: #667eea;
  color: white;
  border: none;
  cursor: pointer;
  font-weight: bold;
}

button:hover {
  background: #5568d3;
}

[data-element] {
  display: block;
  margin: 10px 0;
  padding: 10px;
  background: #f9f9f9;
  border-radius: 4px;
}
```

---

### 5. `init.py` (Virtuell aus DB!)

**⚠️ WICHTIG: `init.py` existiert NICHT im Filesystem!**

Die `init.py` wird aus der Datenbank (Spalte `tasks.code_template`) geladen:

**Admin-Ansicht:**
- `tasks.code_template` → Original-Template
- `tasks.solution_code` → Musterlösung

**Student-Ansicht:**
- `user_tasks.current_code` → Aktueller Schüller-Code
- Falls leer: Fallback auf `tasks.code_template`

**Template für init.py (in DB):**
```python
import idegui as ui

#Init
# Globale Variablen hier definieren
counter = 0

# Traditional Mode: Handler-Funktion
def handle_traditional():
    trigger = ui.get("__trigger__", "")
    if trigger == "increment":
        # Logic here
        pass

# Event-Driven Mode: Direkte Funktionen
def increment(trigger):
    global counter
    counter += 1
    ui.set("output", f"Counter: {counter}")

# Initial UI setup
ui.set("output", "Bereit...")

# Traditional mode dispatch (nur wenn trigger gesetzt)
handle_traditional()
```

---

## 🚀 Task-Erstellung via PHP

**Komplettes Beispiel:**

```php
<?php
require_once __DIR__ . '/config/database.php';

$pdo = getPdoConnection();

// 1. Task in DB anlegen
$code_template = <<<'PYTHON'
import idegui as ui

#Init
result = 0

def calculate(trigger):
    global result
    a = float(ui.get('num1', '0'))
    b = float(ui.get('num2', '0'))
    result = a + b
    ui.set('result', f"Ergebnis: {result}")

ui.set('result', 'Bereit...')
PYTHON;

$sql = "INSERT INTO tasks (
    assignment_id,
    title,
    task_type,
    folderstructure,
    allow_code_ui_web_edit,
    code_template,
    solution_code,
    position
) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    1,                          // assignment_id
    'Meine Code-UI Aufgabe',    // title
    'code_ui',                  // task_type
    1,                          // folderstructure
    1,                          // allow_code_ui_web_edit
    $code_template,             // code_template
    $code_template,             // solution_code
    100                         // position
]);

$taskId = $pdo->lastInsertId();

// 2. Ordner erstellen
$taskFolder = __DIR__ . "/storage/tasks/folders/task_$taskId";
mkdir($taskFolder, 0755, true);

// 3. Dateien erstellen
// .file-policies.json
file_put_contents("$taskFolder/.file-policies.json", json_encode([
    'files' => [
        'idegui.py' => ['read_only' => true],
        'index.html' => ['read_only' => false],
        'style.css' => ['read_only' => false]
    ]
], JSON_PRETTY_PRINT));

// idegui.py (read-only API doc)
file_put_contents("$taskFolder/idegui.py", <<<'PYTHON'
"""idegui - API Documentation"""
def get(name, default=""): return default
def set(name, value): return str(value)
def print(container, *args, sep=' ', end='\n'): pass
def reset(container): pass
class _Trigger:
    name = ""
    value = ""
trigger = _Trigger()
__all__ = ["get", "set", "print", "reset", "trigger"]
PYTHON
);

// index.html
file_put_contents("$taskFolder/index.html", <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Task</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <input type="number" data-element="num1" placeholder="Zahl 1">
    <input type="number" data-element="num2" placeholder="Zahl 2">
    <button data-function="calculate">Berechnen</button>
    <div data-element="result">Ergebnis: -</div>
  </div>
</body>
</html>
HTML
);

// style.css
file_put_contents("$taskFolder/style.css", <<<'CSS'
body { font-family: sans-serif; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; }
input, button { padding: 10px; margin: 5px; }
button { background: #667eea; color: white; border: none; }
[data-element] { margin: 10px 0; padding: 10px; background: #f9f9f9; }
CSS
);

echo "✓ Task $taskId erfolgreich erstellt!\n";
?>
```

---

## 📋 Checkliste beim Task-Anlegen

**Filesystem:**
- [ ] Ordner `storage/tasks/folders/task_XXX/` existiert
- [ ] `.file-policies.json` vorhanden
- [ ] `idegui.py` vorhanden (read-only)
- [ ] `index.html` vorhanden
- [ ] `style.css` vorhanden
- [ ] **KEINE** `init.py` im Filesystem!

**Datenbank:**
- [ ] Task in `tasks` Tabelle angelegt
- [ ] `task_type = 'code_ui'`
- [ ] `folderstructure = 1`
- [ ] `code_template` enthält Python-Code mit `import idegui as ui`
- [ ] `solution_code` enthält Musterlösung
- [ ] `allow_code_ui_web_edit = 1` (wenn HTML/CSS editierbar)

**HTML (index.html):**
- [ ] Alle Inputs/Outputs haben `data-element="name"`
- [ ] Buttons haben entweder `data-run-python="true" data-run-name="X"` ODER `data-function="X"`

**Python (code_template in DB):**
- [ ] Beginnt mit `import idegui as ui`
- [ ] Funktionen für Event-Driven Mode haben Parameter `trigger`
- [ ] Globale Variablen im `#Init` Block deklariert

---

## 🔍 Debugging

**Task lädt nicht:**
→ Prüfe: Existiert der Ordner `storage/tasks/folders/task_XXX/`?
→ Prüfe: Sind alle 4 Dateien vorhanden?

**HTML wird nicht angezeigt:**
→ Prüfe: `folderstructure = 1` in der DB?
→ Prüfe: `index.html` existiert?

**Python-Code läuft nicht:**
→ Prüfe: `code_template` in DB enthält `import idegui as ui`?
→ Prüfe: Keine `init.py` im Filesystem (muss virtuell sein)?

**Buttons funktionieren nicht:**
→ Prüfe: `data-run-python="true"` ODER `data-function="X"` gesetzt?
→ Prüfe: Python-Funktionen matchen die Trigger-Namen?

---

## 📚 Siehe auch

- [ARCHITECTURE_CODE_UI_MODES.md](ARCHITECTURE_CODE_UI_MODES.md) - Zwei Modi (Traditional vs Event-Driven)
- [public/demo-both-modes.html](public/demo-both-modes.html) - Visuelle Erklärung
- Task 169, 170, 171 - Beispiel-Tasks

---

**Erstellt:** März 2026  
**Status:** ✅ Produktionsstandard
