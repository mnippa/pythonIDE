<?php
/**
 * CLI Script to create MWST Rechner task as code_ui
 */

require_once __DIR__ . '/config/database.php';

$pdo = getPdoConnection();

// Create the task
$taskData = [
    'id' => 21,
    'assignment_id' => 1, // Assuming assignment 1 exists (main Python course)
    'title' => 'MwSt-Rechner (Code UI)',
    'description' => 'Erstelle einen interaktiven MWST-Rechner mit HTML-GUI und Python-Logik',
    'position' => 21,
    'problem_type' => 'code_completion',
    'task_type' => 'code_ui',
    'task_text' => <<<'TEXT'
# MwSt-Rechner mit Code UI

Erstelle einen **interaktiven MwSt-Rechner** mit einem HTML-Interface und Python-Backend.

## Anforderungen:

1. **HTML-Interface** (index.html):
   - Input-Feld für Nettopreis
   - Input-Feld für MwSt-Satz (Standard: 19%)
   - Button zum Berechnen
   - Ausgabe-Bereich für Ergebnis

2. **Python-Logik** (init.py):
   - Auslesen der eingaben über idegui
   - Berechnung: Bruttopreis = Nettopreis × (1 + MwSt/100)
   - Berechnung: MwSt-Betrag = Nettopreis × (MwSt/100)
   - Ausgabe mit idegui

3. **Styling** (style.css):
   - Schönes Design für den Calculator
   - Responsive Layout

## Beispiel:
- Nettopreis: 100 Euro
- MwSt-Satz: 19%
- Bruttopreis: 119.00 Euro
- MwSt-Betrag: 19.00 Euro
TEXT,
    'code_template' => <<<'CODE'
# MwSt-Rechner mit Code UI
# Verwende idegui um mit dem HTML zu interagieren

import idegui as ui

# Hier Code schreiben
CODE,
    'solution_code' => <<<'SOLUTION'
import idegui as ui

ui.title("MwSt-Rechner (19%)")

# Eingabefelder
netto_str = ui.text_input("Nettopreis (Euro):", "100")
mwst_str = ui.text_input("MwSt-Satz (%):", "19")

# Umwandlung in Zahlen
try:
    netto = float(netto_str)
    mwst_satz = float(mwst_str)
    
    # Berechnung
    mwst_betrag = netto * (mwst_satz / 100)
    brutto = netto + mwst_betrag
    
    # Ausgabe
    ui.text(f"Nettopreis: {netto:.2f} €")
    ui.text(f"MwSt ({mwst_satz}%): {mwst_betrag:.2f} €")
    ui.text(f"—" * 20)
    ui.text(f"Bruttopreis: {brutto:.2f} €")
    
except ValueError:
    ui.text("❌ Bitte geben Sie gültige Zahlen ein!")
SOLUTION,
    'hint1' => 'Nutze idegui.text_input() für die Eingabefelder',
    'hint2' => 'Formel: Bruttopreis = Nettopreis + (Nettopreis × MwSt-Satz / 100)',
    'hint3' => 'Konvertiere die Eingaben mit float()',
    'stoff' => 'MwSt (Mehrwertsteuer) wird auf den Nettopreis addiert. Die Formel lautet: Bruttopreis = Nettopreis × (1 + MwSt-Satz/100)',
    'max_attempts' => 10,
    'max_iterations' => 1,
    'show_solution' => 1,
    'show_solution_code' => 1,
];

// Check if task already exists
$checkStmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
$checkStmt->execute([$taskData['id']]);
$exists = $checkStmt->fetch();

if ($exists) {
    // Update existing task
    $updateStmt = $pdo->prepare(
        "UPDATE tasks SET 
            assignment_id = ?, title = ?, description = ?, position = ?, 
            problem_type = ?, task_type = ?, task_text = ?, code_template = ?, solution_code = ?,
            hint1 = ?, hint2 = ?, hint3 = ?, stoff = ?,
            max_attempts = ?, iterations_count = ?, show_solution = ?, show_solution_code = ?,
            updated_at = NOW()
        WHERE id = ?"
    );
    
    $success = $updateStmt->execute([
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
        $taskData['id']
    ]);
    
    if (!$success) {
        echo "❌ Database error: " . $updateStmt->errorInfo()[2] . "\n";
        exit(1);
    }
    echo "✨ Task #21 updated\n";
} else {
    // Insert new task
    $stmt = $pdo->prepare(
        "INSERT INTO tasks (
            id, assignment_id, title, description, position, 
            problem_type, task_type, task_text, code_template, solution_code,
            hint1, hint2, hint3, stoff,
            max_attempts, iterations_count, show_solution, show_solution_code,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
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
    echo "✅ Task #21 created\n";
}

// Create code_ui scaffold folder and files
$taskId = $taskData['id'];
$triggerFolder = __DIR__ . '/storage/tasks/folders/task_' . $taskId;

if (!is_dir($triggerFolder)) {
    mkdir($triggerFolder, 0755, true);
}

// Create index.html with calculator UI
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
      <h2>🧮 MwSt-Rechner</h2>
      
      <div class="form-group">
        <label for="netto">Nettopreis (€):</label>
        <input type="number" id="netto" placeholder="z.B. 100" step="0.01" min="0" />
      </div>
      
      <div class="form-group">
        <label for="mwst">MwSt-Satz (%):</label>
        <input type="number" id="mwst" placeholder="z.B. 19" step="0.1" min="0" max="100" value="19" />
      </div>
      
      <button id="calculate-btn">Berechnen</button>
      
      <div id="result" class="result" style="display: none;">
        <div class="result-item">
          <span class="label">Nettopreis:</span>
          <span class="value" id="result-netto">-</span>
        </div>
        <div class="result-item">
          <span class="label">MwSt-Betrag:</span>
          <span class="value" id="result-mwst">-</span>
        </div>
        <div class="result-item highlight">
          <span class="label">Bruttopreis:</span>
          <span class="value" id="result-brutto">-</span>
        </div>
      </div>
    </div>
    
    <div id="idegui-root" data-idegui-root="true"></div>
    <div id="idegui-output" data-idegui-output="true"></div>
  </div>
  
  <script>
    document.getElementById('calculate-btn').addEventListener('click', function() {
      const netto = parseFloat(document.getElementById('netto').value);
      const mwst = parseFloat(document.getElementById('mwst').value);
      
      if (isNaN(netto) || netto < 0) {
        alert('Bitte geben Sie einen gültigen Nettopreis ein!');
        return;
      }
      
      const mwst_betrag = netto * (mwst / 100);
      const brutto = netto + mwst_betrag;
      
      document.getElementById('result-netto').textContent = netto.toFixed(2) + ' €';
      document.getElementById('result-mwst').textContent = mwst_betrag.toFixed(2) + ' €';
      document.getElementById('result-brutto').textContent = brutto.toFixed(2) + ' €';
      document.getElementById('result').style.display = 'block';
    });
    
    document.getElementById('netto').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') document.getElementById('calculate-btn').click();
    });
  </script>
</body>
</html>
HTML;

file_put_contents($triggerFolder . '/index.html', $indexHtml);

// Create style.css with calculator styling
$styleCss = <<<'CSS'
/* CODE_UI_TEMPLATE_VERSION: 1.1.0 */
.code-ui-wrapper {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    margin: 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calculator {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 400px;
    width: 100%;
}

.calculator h2 {
    margin: 0 0 24px 0;
    color: #333;
    font-size: 28px;
    text-align: center;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    margin-top: 8px;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

button:active {
    transform: translateY(0);
}

.result {
    margin-top: 24px;
    padding: 16px;
    background: #f5f5f5;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.result-item:last-child {
    border-bottom: none;
}

.result-item.highlight {
    background: #e8f1ff;
    padding: 8px 12px;
    margin: 8px -16px -16px -16px;
    border-radius: 0 0 8px 8px;
    font-weight: 600;
}

.result-item .label {
    color: #666;
    font-weight: 500;
}

.result-item .value {
    color: #333;
    font-weight: 600;
    font-size: 18px;
}

#idegui-root {
    margin-top: 24px;
    min-height: 180px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 12px;
    background: white;
}

#idegui-output {
    margin-top: 12px;
    font-size: 14px;
    color: #374151;
}
CSS;

file_put_contents($triggerFolder . '/style.css', $styleCss);

// Create idegui.py template
$ideguiPy = <<<'PY'
# CODE_UI_TEMPLATE_VERSION: 1.1.0
# Das idegui Modul für interaktive UI-Komponenten

def title(text):
    """Display a title/heading"""
    return {"type": "title", "text": text}

def text(value):
    """Display text output"""
    return {"type": "text", "text": str(value)}

def text_input(label="", default=""):
    """Get text input from user"""
    return {"type": "text_input", "label": label, "default": default}

def number_input(label="", default=0):
    """Get number input from user"""
    return {"type": "number_input", "label": label, "default": default}
PY;

file_put_contents($triggerFolder . '/idegui.py', $ideguiPy);

// Create code_ui.template.json
$templateJson = json_encode([
    'type' => 'code_ui',
    'template_version' => '1.1.0',
    'generated_at' => date('c'),
    'files' => ['index.html', 'style.css', 'idegui.py', 'code_ui.template.json']
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($triggerFolder . '/code_ui.template.json', $templateJson);

echo "✅ Task #21 'MwSt-Rechner (Code UI)' created successfully!\n";
echo "📁 Scaffold created in storage/tasks/folders/task_21/\n";
echo "📝 Files:\n";
echo "   - index.html (Calculator UI)\n";
echo "   - style.css (Gradient styling)\n";
echo "   - idegui.py (Template)\n";
echo "   - code_ui.template.json (Metadata)\n";
