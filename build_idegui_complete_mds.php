<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();
$projectId = 48;
$folderIds = [119, 120, 121, 122, 123, 124];
$targetDir = __DIR__ . '/docs/goldspiel_sourcepack/idegui_teil3_complete';

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

function codeLang(string $fileName): string {
    $lower = strtolower($fileName);
    if (substr($lower, -3) === '.py') return 'python';
    if (substr($lower, -5) === '.html') return 'html';
    if (substr($lower, -4) === '.css') return 'css';
    if (substr($lower, -3) === '.md') return 'markdown';
    return '';
}

$allSteps = [];

foreach ($folderIds as $folderId) {
    $stmtFolder = $conn->prepare('SELECT id, name FROM project_folders WHERE project_id=? AND id=? LIMIT 1');
    $stmtFolder->bind_param('ii', $projectId, $folderId);
    $stmtFolder->execute();
    $folderRes = $stmtFolder->get_result();
    $folder = $folderRes ? $folderRes->fetch_assoc() : null;
    if (!$folder) {
        continue;
    }

    $stmtFiles = $conn->prepare('SELECT name, content FROM project_files WHERE project_id=? AND folder_id=? ORDER BY FIELD(name, "README.md", "index.html", "init.py", "style.css"), name');
    $stmtFiles->bind_param('ii', $projectId, $folderId);
    $stmtFiles->execute();
    $filesRes = $stmtFiles->get_result();

    $files = [];
    while ($row = $filesRes->fetch_assoc()) {
        $files[] = $row;
    }

    $folderName = (string)$folder['name'];
    $stepPrefix = substr($folderName, 0, 2);
    $safeFileName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $folderName);
    $mdFileName = $safeFileName . '.md';

    $md = "# IDEGUI Teil 3 - {$folderName}\n\n";
    $md .= "Projekt: 48 (IDEGUI Teil 3 - Taschenrechner systematisch)\n\n";
    $md .= "## Inhalte\n\n";

    foreach ($files as $file) {
        $name = (string)$file['name'];
        $content = (string)$file['content'];
        $lang = codeLang($name);

        $md .= "### {$name}\n\n";
        $md .= "```{$lang}\n";
        $md .= rtrim($content) . "\n";
        $md .= "```\n\n";
    }

    file_put_contents($targetDir . '/' . $mdFileName, $md);

    $allSteps[] = [
        'folder_id' => $folderId,
        'folder_name' => $folderName,
        'file_name' => $mdFileName,
        'step_prefix' => $stepPrefix,
    ];

    echo "Wrote {$mdFileName}\n";
}

usort($allSteps, function ($a, $b) {
    return strcmp($a['folder_name'], $b['folder_name']);
});

$index = "# IDEGUI Teil 3 - Vollstaendige MD-Sammlung\n\n";
$index .= "Quelle: Projekt 48 (IDEGUI Teil 3 - Taschenrechner systematisch)\n\n";
$index .= "Diese Sammlung wurde direkt aus den aktuellen Projektdateien erzeugt.\n\n";
$index .= "## Schritte\n\n";

foreach ($allSteps as $step) {
    $index .= "- {$step['folder_name']} -> {$step['file_name']}\n";
}

$index .= "\n## Didaktische Entwicklung\n\n";
$index .= "- 01: Data-Element-Basis\n";
$index .= "- 02: Linearer Ablauf mit data-run\n";
$index .= "- 03: Event-Driven Basis mit data-function\n";
$index .= "- 04: Globale Variable + Verlaufsanzeige\n";
$index .= "- 05: Operatoren als Tasten\n";
$index .= "- 06: Kompletttastatur (inkl. geteilt und Vorzeichen)\n";

file_put_contents($targetDir . '/INDEX.md', $index);
echo "Wrote INDEX.md\n";
