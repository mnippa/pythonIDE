<?php
/**
 * Create simplified MwSt Task with data-attributes (no JS)
 */

require_once __DIR__ . '/config/database.php';

$pdo = getPdoConnection();

// Delete old Task #21 first
$pdo->prepare('DELETE FROM tasks WHERE id = 21')->execute();

$taskData = [
    'id' => 21,
    'assignment_id' => 21,
    'title' => 'MwSt-Rechner mit Data-Attributen',
    'description' => 'Einfacher interaktiver MwSt-Rechner ohne JavaScript',
    'position' => 21,
    'problem_type' => 'code_completion',
    'task_type' => 'code_ui',
    'task_text' => <<<'TEXT'
# MwSt-Rechner mit Data-Attributen

Erstelle einen interaktiven MwSt-Rechner mit HTML + Python – ganz ohne JavaScript-Events.

## Ziel
Der Benutzer gibt Nettopreis und MwSt-Satz ein. Python berechnet dann:
- MwSt-Betrag
- Bruttopreis

## Schritt-für-Schritt

### 1) HTML vorbereiten
Nutze `data-input` für Eingabefelder und `data-output` für Ergebnisfelder.

Beispiele:
- `<input data-input="netto">`
- `<input data-input="mwst">`
- `<span data-output="result_brutto">-</span>`

### 2) Werte in Python lesen
Mit `ui.get_input_value("...")` liest du den Inhalt der Felder.

### 3) In Zahlen umwandeln
Da Eingaben als Text kommen, mit `float(...)` umwandeln.

### 4) Berechnen
- `mwst_betrag = netto * (mwst / 100)`
- `brutto = netto + mwst_betrag`

### 5) Ergebnisse ins HTML schreiben
Mit `ui.set_output("name", wert)` die Ausgabezonen füllen.

### 6) Fehler abfangen
Mit `try/except ValueError` ungültige Eingaben sauber behandeln.
TEXT,
    'code_template' => <<<'CODE'
import idegui as ui

# input_netto = ui.get_input_value("netto")
# input_mwst = ui.get_input_value("mwst")

# Hier dein Code...
# Nutze dann:
# ui.set_output("result_netto", ...)
# ui.set_output("result_mwst", ...)
# ui.set_output("result_brutto", ...)
CODE,
    'solution_code' => <<<'SOLUTION'
import idegui as ui

# Eingaben lesen
netto_str = ui.get_input_value("netto")
mwst_str = ui.get_input_value("mwst")

# Umwandeln
try:
    netto = float(netto_str)
    mwst = float(mwst_str)
    
    # Berechnung
    mwst_betrag = netto * (mwst / 100)
    brutto = netto + mwst_betrag
    
    # Ausgaben setzen
    ui.set_output("result_netto", f"{netto:.2f} €")
    ui.set_output("result_mwst", f"{mwst_betrag:.2f} €")
    ui.set_output("result_brutto", f"{brutto:.2f} €")
    
except ValueError:
    ui.set_output("result_error", "❌ Ungültige Eingabe!")
SOLUTION,
    'hint1' => 'Nutze ui.get_input_value("netto") um Werte zu lesen',
    'hint2' => 'Nutze ui.set_output("name", wert) um Ergebnisse anzuzeigen',
    'hint3' => 'Keine JavaScript-Events! Python macht alles.',
    'stoff' => <<<'STOFF'
## Stoff: Arbeiten mit HTML-data-Attributen und Python

Diese Aufgabe zeigt ein einfaches, aber sehr wichtiges Prinzip: **Trennung von Oberfläche und Logik**.

### A) Rolle von HTML
HTML definiert nur die Oberfläche:
- Eingaben: `data-input="..."`
- Ausgaben: `data-output="..."`

HTML rechnet nicht selbst, sondern stellt Felder bereit, die Python lesen/beschreiben kann.

### B) Rolle von Python
Python übernimmt die gesamte Logik:
1. Eingaben lesen (`ui.get_input_value`)
2. Datentypen umwandeln (`float`)
3. Fachliche Berechnung durchführen
4. Ergebnis zurück in die Oberfläche schreiben (`ui.set_output`)

### C) Zuordnung (Mapping)
Die Namen müssen zusammenpassen:
- HTML: `data-input="netto"`  ↔ Python: `ui.get_input_value("netto")`
- HTML: `data-output="result_brutto"` ↔ Python: `ui.set_output("result_brutto", ... )`

### D) Typischer Ablauf in jeder solchen Aufgabe
**Input lesen → validieren/umwandeln → berechnen → Output setzen**

### E) Vorteile dieser Methode
- Kein komplexes Event-Handling in JavaScript
- Klarer Fokus auf Python-Denken
- Gut erweiterbar (z. B. Rabatt, mehrere Steuersätze, Rundungsregeln)

### F) Merksatz
**HTML zeigt Felder an, Python denkt und rechnet.**
STOFF,
    'max_attempts' => 10,
    'max_iterations' => 1,
    'show_solution' => 1,
    'show_solution_code' => 1,
];

// Insert task
$stmt = $pdo->prepare(
    "INSERT INTO tasks (
        id, assignment_id, title, description, position, 
        problem_type, task_type, task_text, code_template, solution_code,
        hint1, hint2, hint3, stoff,
        max_attempts, iterations_count, show_solution, show_solution_code,
        folderstructure, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())"
);

$success = $stmt->execute([
    $taskData['id'],
    $taskData['assignment_id'],
    $taskData['title'],
    $taskData['description'],
    $taskData['position'],
    $taskData['problem_type'],
    $taskData['task_type'],
    $taskData['task_text'],
    $taskData['code_template'],
    $taskData['solution_code'],
    $taskData['hint1'],
    $taskData['hint2'],
    $taskData['hint3'],
    $taskData['stoff'],
    $taskData['max_attempts'],
    $taskData['max_iterations'],
    $taskData['show_solution'],
    $taskData['show_solution_code'],
]);

if (!$success) {
    echo "❌ Database error: " . $stmt->errorInfo()[2] . "\n";
    exit(1);
}

// Create simple scaffold
$taskId = 21;
$folderPath = __DIR__ . '/storage/tasks/folders/task_' . $taskId;
if (!is_dir($folderPath)) {
    mkdir($folderPath, 0755, true);
}

// Simplified HTML - nur data-Attribute, kein JS!
$indexHtml = <<<'HTML'
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>MwSt-Rechner</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <div class="code-ui-wrapper">
    <div class="calculator">
      <h2>🧮 MwSt-Rechner (einfache Version)</h2>
      
      <!-- Eingabefelder mit data-Attributen -->
      <div class="form-group">
        <label for="netto">Nettopreis (€):</label>
        <input type="number" id="netto" data-input="netto" 
               placeholder="z.B. 100" step="0.01" min="0" />
      </div>
      
      <div class="form-group">
        <label for="mwst">MwSt-Satz (%):</label>
        <input type="number" id="mwst" data-input="mwst" 
               placeholder="z.B. 19" step="0.1" min="0" max="100" value="19" />
      </div>

            <button type="button" data-run-python="true" class="run-btn">Mit Python berechnen</button>
      
      <!-- Ausgabezonen mit data-Attributen -->
      <div class="result">
        <div class="result-item">
          <span class="label">Nettopreis:</span>
          <span class="value" data-output="result_netto">-</span>
        </div>
        <div class="result-item">
          <span class="label">MwSt-Betrag:</span>
          <span class="value" data-output="result_mwst">-</span>
        </div>
        <div class="result-item highlight">
          <span class="label">Bruttopreis:</span>
          <span class="value" data-output="result_brutto">-</span>
        </div>
        <div class="result-item error" hidden>
          <span data-output="result_error"></span>
        </div>
      </div>
    </div>
    
    <div id="idegui-root" data-idegui-root="true"></div>
    <div id="idegui-output" data-idegui-output="true"></div>
  </div>
    <script src="ui-runtime.readonly.js"></script>
</body>
</html>
HTML;

file_put_contents($folderPath . '/index.html', $indexHtml);

// Simple CSS
$styleCss = <<<'CSS'
/* CODE_UI_TEMPLATE_VERSION: 1.1.0 */
.code-ui-wrapper {
    font-family: system-ui, sans-serif;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calculator {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    max-width: 400px;
    width: 100%;
}

.calculator h2 {
    margin: 0 0 20px 0;
    color: #333;
    font-size: 24px;
    text-align: center;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    color: #555;
    font-weight: 500;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
}

.run-btn {
    width: 100%;
    padding: 10px 12px;
    border: none;
    border-radius: 6px;
    background: #4f46e5;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    margin: 4px 0 10px;
}

.run-btn:hover {
    background: #4338ca;
}

.result {
    margin-top: 16px;
    padding: 12px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid #667eea;
}

.result-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px solid #eee;
}

.result-item:last-child {
    border-bottom: none;
}

.result-item.highlight {
    background: #e8f1ff;
    padding: 8px 8px;
    margin: 0 -12px -12px -12px;
    border-radius: 0 0 6px 6px;
    font-weight: 600;
}

.result-item .label {
    color: #666;
}

.result-item .value {
    color: #333;
    font-weight: 600;
}

.result-item.error {
    color: #d32f2f;
}

#idegui-root {
    margin-top: 16px;
    min-height: 120px;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 12px;
    background: white;
}

#idegui-output {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
}
CSS;

file_put_contents($folderPath . '/style.css', $styleCss);

// Minimal idegui template
$ideguiPy = <<<'PY'
# idegui - Simple Data-Attribute Interface

def get_input_value(name):
    """Get value from data-input="name" element"""
    return {"type": "get_input", "name": name}

def set_output(name, value):
    """Set value in data-output="name" element"""
    return {"type": "set_output", "name": name, "value": str(value)}
PY;

file_put_contents($folderPath . '/idegui.py', $ideguiPy);

$runtimeJs = <<<'JS'
/*
    Task Runtime-Hinweis (readonly empfohlen)
    -----------------------------------------
    Die Ausführung wird zentral durch die Plattform gesteuert.
    Button mit data-run-python="true" löst den Python-Run aus.
*/

(function () {
    const enabled = false;
    if (!enabled) return;

    document.addEventListener('click', (event) => {
        const trigger = event.target?.closest?.('[data-run-python="true"]');
        if (!trigger) return;
        event.preventDefault();
        const runButton = document.getElementById('run-btn');
        if (runButton) runButton.click();
    });
})();
JS;

file_put_contents($folderPath . '/ui-runtime.readonly.js', $runtimeJs);

// Metadata
$templateJson = json_encode([
    'type' => 'code_ui',
    'template_version' => '1.1.0',
    'design' => 'data-attributes-only',
    'no_javascript' => true,
    'generated_at' => date('c')
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($folderPath . '/code_ui.template.json', $templateJson);

echo "✅ Task #21 'MwSt-Rechner (Data-Attribute Version)' erstellt!\n";
echo "📁 Dateien:\n";
echo "   ✅ index.html (nur data-Attribute, kein JavaScript)\n";
echo "   ✅ style.css (einfach, lesbar)\n";
echo "   ✅ idegui.py (minimale API)\n";
echo "   ✅ ui-runtime.readonly.js (Ereignislogik-Doku)\n";
echo "   ✅ code_ui.template.json (Metadaten)\n";
echo "\n🎯 Merkmale:\n";
echo "   • Keine JavaScript Event-Handler\n";
echo "   • Einfachere HTML-Struktur\n";
echo "   • Studenten fokussieren sich auf Python\n";
echo "   • Data-Attribute sind leicht zu verstehen\n";
