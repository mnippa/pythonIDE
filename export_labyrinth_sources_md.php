<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'labyrinth_sourcepack';

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

function fetchFilesForFolder(mysqli $conn, int $projectId, int $folderId): array {
    $stmt = $conn->prepare("SELECT id, name, content FROM project_files WHERE project_id = ? AND folder_id = ? ORDER BY name");
    $stmt->bind_param('ii', $projectId, $folderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function endsWith(string $haystack, string $needle): bool {
    $len = strlen($needle);
    if ($len === 0) {
        return true;
    }
    return substr($haystack, -$len) === $needle;
}

function fetchFolderName(mysqli $conn, int $folderId): string {
    $stmt = $conn->prepare("SELECT name FROM project_folders WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $folderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $name = 'unknown';
    if ($row = $res->fetch_assoc()) {
        $name = $row['name'];
    }
    $stmt->close();
    return $name;
}

function codeFenceFor(string $filename): string {
    if (endsWith($filename, '.py')) return 'python';
    if (endsWith($filename, '.md')) return 'markdown';
    if (endsWith($filename, '.txt')) return 'text';
    return '';
}

$projectId = 47;

$steps = [
    [
        'label' => '01',
        'title' => 'Ausgangssituation (manuell)',
        'folderId' => 108,
        'levelsFolderId' => 107,
    ],
    [
        'label' => '02',
        'title' => 'Rechtehandregel',
        'folderId' => 109,
        'levelsFolderId' => 112,
    ],
    [
        'label' => '03',
        'title' => 'BFS (kuerzester Pfad)',
        'folderId' => 113,
        'levelsFolderId' => 114,
    ],
    [
        'label' => '04',
        'title' => 'Demo Rechtehand vs Tremaux',
        'folderId' => 115,
        'levelsFolderId' => 116,
    ],
];

$index = [];
$index[] = '# Labyrinth Source Pack (Projekt 47)';
$index[] = '';
$index[] = 'Dieses Paket enthaelt pro Schritt:';
$index[] = '- die README';
$index[] = '- den vollstaendigen Python-Quellcode';
$index[] = '- die kompletten Labyrinth-Leveldateien';
$index[] = '';
$index[] = 'Exportdatum: ' . date('Y-m-d H:i:s');
$index[] = '';

foreach ($steps as $step) {
    $stepLabel = $step['label'];
    $stepTitle = $step['title'];
    $stepFolderId = $step['folderId'];
    $levelsFolderId = $step['levelsFolderId'];

    $stepFiles = fetchFilesForFolder($conn, $projectId, $stepFolderId);
    $levelFiles = fetchFilesForFolder($conn, $projectId, $levelsFolderId);

    $md = [];
    $md[] = '# Schritt ' . $stepLabel . ' - ' . $stepTitle;
    $md[] = '';
    $md[] = 'Ordner: `' . fetchFolderName($conn, $stepFolderId) . '`';
    $md[] = 'Level-Ordner: `' . fetchFolderName($conn, $levelsFolderId) . '`';
    $md[] = '';
    $md[] = '## README';
    $md[] = '';

    foreach ($stepFiles as $file) {
        if ($file['name'] === 'README.md') {
            $md[] = '```markdown';
            $md[] = rtrim($file['content']);
            $md[] = '```';
            $md[] = '';
            break;
        }
    }

    $md[] = '## Quellcode';
    $md[] = '';
    foreach ($stepFiles as $file) {
        if ($file['name'] === 'README.md') continue;
        $lang = codeFenceFor($file['name']);
        $md[] = '### ' . $file['name'];
        $md[] = '';
        $md[] = '```' . $lang;
        $md[] = rtrim($file['content']);
        $md[] = '```';
        $md[] = '';
    }

    $md[] = '## Leveldateien';
    $md[] = '';
    foreach ($levelFiles as $file) {
        if (!preg_match('/^[0-9][0-9]\\.txt$/', $file['name'])) continue;
        $md[] = '### ' . $file['name'];
        $md[] = '';
        $md[] = '```text';
        $md[] = rtrim($file['content']);
        $md[] = '```';
        $md[] = '';
    }

    $target = $baseDir . DIRECTORY_SEPARATOR . 'schritt_' . $stepLabel . '.md';
    file_put_contents($target, implode(PHP_EOL, $md));

    $index[] = '- `schritt_' . $stepLabel . '.md`: Schritt ' . $stepLabel . ' - ' . $stepTitle;
}

$index[] = '';
$index[] = '## Root-Datei';
$index[] = '';
$stmt = $conn->prepare("SELECT content FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = 'init.py' LIMIT 1");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $index[] = '```python';
    $index[] = rtrim($row['content']);
    $index[] = '```';
}
$stmt->close();

file_put_contents($baseDir . DIRECTORY_SEPARATOR . 'INDEX.md', implode(PHP_EOL, $index));

$conn->close();

echo "OK export to: " . $baseDir . PHP_EOL;
?>