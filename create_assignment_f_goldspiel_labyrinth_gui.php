<?php
/**
 * Create Assignment F: Goldspiel Labyrinth GUI
 * With Task 01_Spielfeld as folder-based structure
 * 
 * Run: php create_assignment_f_goldspiel_labyrinth_gui.php
 */

require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();

try {
    // === 1. Create Assignment ===
    $assignmentTitle = 'F Goldspiel Labyrinth GUI';
    $assignmentDescription = 'In diesem Assignment implementierst du schrittweise ein Labyrinth-Spiel mit Goldsammlung. Beginnend mit der Spielfelddarstellung bis zur vollständigen Spielmechanik mit Optimalpfad-Vergleich.';
    $createdBy = 1;
    
    $assignmentStmt = $conn->prepare('
        INSERT INTO assignments 
        (title, description, created_by, difficulty, is_active, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ');

    $difficulty = 'intermediate';
    $isActive = 1;
    $assignmentStmt->bind_param('ssisi', $assignmentTitle, $assignmentDescription, $createdBy, $difficulty, $isActive);

    if (!$assignmentStmt->execute()) {
        throw new Exception('Failed to create assignment: ' . $assignmentStmt->error);
    }
    
    $assignmentId = $conn->insert_id;
    echo "✅ Assignment created: ID $assignmentId - $assignmentTitle\n";
    
    // === 2. Create Task 01_Spielfeld ===
    $taskTitle = '01_Spielfeld';
    $taskText = 'Importiere das Spielfeld und stelle es dar. Das Spielfeld ist eine 9x9 Gittermatrix mit verschiedenen Feldtypen (Leer, Wand, Start, Gold, Ziel).';
    $taskDescription = 'Du erhältst diese Dateien:\n- `spielfeld.py`: Enthält das 9x9-Spielfeld als Liste von Listen\n- `function.py`: Renderer für die Spielfeldvisualisierung\n- `main.py`: Hauptprogramm\n\nFeldwerte:\n- 0 = leer\n- 1 = Wand (⬛)\n- 2 = Start (🟦)\n- 7 = Gold (🪙)\n- 9 = Ziel (🚪)\n\nDeine Aufgabe: Führe `main.py` aus und stelle sicher, dass das Spielfeld korrekt angezeigt wird.';
    $stoff = '<h4>Grundkonzepte</h4><ul><li>Listen von Listen (2D-Arrays) für Spielfelder</li><li>Funktion für Rendering/Visualisierung</li><li>Import von Modulen</li></ul>';
    
    $taskStmt = $conn->prepare('
        INSERT INTO tasks 
        (assignment_id, title, task_text, description, stoff, task_type, folderstructure, 
         position, code_template, solution_code, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ');
    
    $position = 1;
    $codeTemplate = 'from spielfeld import SPIELFELD\nfrom function import render\n\nrender(SPIELFELD)\n';
    $solutionCode = $codeTemplate;

    $taskType = 'code';
    $folderstructure = 1;
    $taskStmt->bind_param(
        'isssssiiss',
        $assignmentId,
        $taskTitle,
        $taskText,
        $taskDescription,
        $stoff,
        $taskType,
        $folderstructure,
        $position,
        $codeTemplate,
        $solutionCode
    );

    if (!$taskStmt->execute()) {
        throw new Exception('Failed to create task: ' . $taskStmt->error);
    }
    
    $taskId = $conn->insert_id;
    echo "✅ Task created: ID $taskId - $taskTitle\n";
    
    // === 3. Create folder structure ===
    $folderPath = __DIR__ . '/storage/tasks/folders/task_' . $taskId;
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
        echo "📁 Folder created: $folderPath\n";
    }
    
    // === 4. Copy project files from documentation ===
    $sourceDoc = __DIR__ . '/docs/goldspiel_sourcepack/vorlesung7_schritt_01.md';
    
    if (!file_exists($sourceDoc)) {
        throw new Exception("Source documentation not found: $sourceDoc");
    }
    
    // === 5. Create files for 01_Spielfeld ===
    
    // spielfeld.py
    $spielfeldContent = <<<'PYTHON'
# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]
PYTHON;
    
    file_put_contents("$folderPath/spielfeld.py", $spielfeldContent);
    echo "   ✓ spielfeld.py\n";
    
    // function.py
    $functionContent = <<<'PYTHON'
SYMBOLE = {
    0: '  ',
    1: '⬛',
    2: '🟦',
    7: '🪙',
    9: '🚪',
}


def render(spielfeld):
    for zeile in spielfeld:
        ausgabe = ''
        for wert in zeile:
            ausgabe = ausgabe + SYMBOLE[wert]
        print(ausgabe)
PYTHON;
    
    file_put_contents("$folderPath/function.py", $functionContent);
    echo "   ✓ function.py\n";
    
    // main.py (init.py wird vom System generiert)
    $mainContent = <<<'PYTHON'
from spielfeld import SPIELFELD
from function import render

render(SPIELFELD)
PYTHON;
    
    file_put_contents("$folderPath/main.py", $mainContent);
    echo "   ✓ main.py\n";
    
    // README.md
    $readmeContent = <<<'MARKDOWN'
# 01 Spielfeld

In diesem ersten Schritt wird nur das Spielfeld dargestellt.
Es gibt noch keinen Spieler und noch keine Bewegung.

## Dateien

- `spielfeld.py`: Enthält das 9x9-Spielfeld als Liste von Listen
- `function.py`: Enthält die render()-Funktion für die Visualisierung
- `main.py`: Hauptprogramm - ruft render() auf

## Feldwerte

- `0` = leer (zwei Leerzeichen)
- `1` = Wand (⬛)
- `2` = Start (🟦)
- `7` = Gold (🪙)
- `9` = Ziel (🚪)

## Aufgabe

Stelle das Spielfeld dar, indem du `main.py` ausführst.
MARKDOWN;
    
    file_put_contents("$folderPath/README.md", $readmeContent);
    echo "   ✓ README.md\n";
    
    // Create .file-policies.json for readonly policy
    $policiesContent = [
        'policies' => [
            [
                'path' => 'spielfeld.py',
                'readonly' => true,
                'reason' => 'Spielfeld-Datenstruktur'
            ],
            [
                'path' => 'function.py',
                'readonly' => true,
                'reason' => 'Renderer-Funktion'
            ],
            [
                'path' => 'README.md',
                'readonly' => true,
                'reason' => 'Aufgabenbeschreibung'
            ]
        ]
    ];
    
    file_put_contents(
        "$folderPath/.file-policies.json",
        json_encode($policiesContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    echo "   ✓ .file-policies.json (readonly policies)\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Assignment F: Goldspiel Labyrinth GUI erfolgreich erstellt!\n";
    echo str_repeat("=", 60) . "\n";
    echo "Assignment ID: $assignmentId\n";
    echo "Task ID: $taskId\n";
    echo "Task Title: $taskTitle\n";
    echo "Folder Path: $folderPath\n";
    echo "\nDie Dateien sind bereit für Schüler zum Bearbeiten.\n";
    echo "Schüler können main.py ausführen und das Spielfeld sehen.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
