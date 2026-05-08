<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'goldspiel_sourcepack';

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

function endsWith($haystack, $needle) {
    $len = strlen($needle);
    if ($len === 0) return true;
    return substr($haystack, -$len) === $needle;
}

function codeFenceFor($filename) {
    if (endsWith($filename, '.py')) return 'python';
    if (endsWith($filename, '.md')) return 'markdown';
    if (endsWith($filename, '.txt')) return 'text';
    if (endsWith($filename, '.html')) return 'html';
    if (endsWith($filename, '.css')) return 'css';
    return '';
}

function fetchFolders(mysqli $conn, $projectId) {
    $stmt = $conn->prepare('SELECT id, name, parent_folder_id FROM project_folders WHERE project_id = ? ORDER BY name');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function fetchFilesByFolderIds(mysqli $conn, $projectId, $folderIds) {
    if (count($folderIds) === 0) return [];
    $in = implode(',', array_fill(0, count($folderIds), '?'));
    $types = str_repeat('i', count($folderIds) + 1);
    $sql = "SELECT id, folder_id, name, content FROM project_files WHERE project_id = ? AND folder_id IN ($in) ORDER BY folder_id, name";
    $stmt = $conn->prepare($sql);

    $params = array_merge([$projectId], $folderIds);
    $bind = [];
    $bind[] = & $types;
    foreach ($params as $k => $v) $bind[] = & $params[$k];
    call_user_func_array([$stmt, 'bind_param'], $bind);

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function fetchRootFiles(mysqli $conn, $projectId) {
    $stmt = $conn->prepare('SELECT id, name, content FROM project_files WHERE project_id = ? AND folder_id IS NULL ORDER BY name');
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
    return $rows;
}

function collectDescendantFolderIds($allFolders, $rootId) {
    $desc = [$rootId];
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($allFolders as $f) {
            if ($f['parent_folder_id'] === null) continue;
            if (in_array((int)$f['parent_folder_id'], $desc, true) && !in_array((int)$f['id'], $desc, true)) {
                $desc[] = (int)$f['id'];
                $changed = true;
            }
        }
    }
    return $desc;
}

$index = [];
$index[] = '# Goldspiel Source Pack';
$index[] = '';
$index[] = 'Enthaelt vollstaendige Quellen fuer:';
$index[] = '- Projekt 45: Vorlesung 7 (Schritte 01-07)';
$index[] = '- Projekt 46: IDEGUI Demo (Event Driven)';
$index[] = '';
$index[] = 'Exportdatum: ' . date('Y-m-d H:i:s');
$index[] = '';

// Project 45 step export
$project45 = 45;
$folders45 = fetchFolders($conn, $project45);
$stepFolders = [];
foreach ($folders45 as $f) {
    if ($f['parent_folder_id'] === null && preg_match('/^0[0-9]_/', $f['name'])) {
        $stepFolders[] = $f;
    }
}
usort($stepFolders, function($a, $b){ return strcmp($a['name'], $b['name']); });

foreach ($stepFolders as $sf) {
    $stepName = $sf['name'];
    $stepLabel = substr($stepName, 0, 2);
    $allIds = collectDescendantFolderIds($folders45, (int)$sf['id']);
    $files = fetchFilesByFolderIds($conn, $project45, $allIds);

    $doc = [];
    $doc[] = '# Projekt 45 - Schritt ' . $stepLabel . ' (' . $stepName . ')';
    $doc[] = '';
    $doc[] = '## Inhalte';
    $doc[] = '';

    // README first
    foreach ($files as $file) {
        if ($file['name'] === 'README.md') {
            $doc[] = '### README.md';
            $doc[] = '';
            $doc[] = '```markdown';
            $doc[] = rtrim($file['content']);
            $doc[] = '```';
            $doc[] = '';
        }
    }

    foreach ($files as $file) {
        if ($file['name'] === 'README.md') continue;
        $doc[] = '### ' . $file['name'];
        $doc[] = '';
        $doc[] = '```' . codeFenceFor($file['name']);
        $doc[] = rtrim($file['content']);
        $doc[] = '```';
        $doc[] = '';
    }

    $target = $baseDir . DIRECTORY_SEPARATOR . 'vorlesung7_schritt_' . $stepLabel . '.md';
    file_put_contents($target, implode(PHP_EOL, $doc));
    $index[] = '- `vorlesung7_schritt_' . $stepLabel . '.md`';
}

$index[] = '';
$index[] = '## Projekt 45 Root-Dateien';
$index[] = '';
$root45 = fetchRootFiles($conn, $project45);
foreach ($root45 as $f) {
    $index[] = '### ' . $f['name'];
    $index[] = '';
    $index[] = '```' . codeFenceFor($f['name']);
    $index[] = rtrim($f['content']);
    $index[] = '```';
    $index[] = '';
}

// Project 46 export
$project46 = 46;
$root46 = fetchRootFiles($conn, $project46);
$demo = [];
$demo[] = '# Projekt 46 - IDEGUI Demo (Goldspiel Event Driven)';
$demo[] = '';
$demo[] = '## Dateien';
$demo[] = '';
foreach ($root46 as $f) {
    $demo[] = '### ' . $f['name'];
    $demo[] = '';
    $demo[] = '```' . codeFenceFor($f['name']);
    $demo[] = rtrim($f['content']);
    $demo[] = '```';
    $demo[] = '';
}
file_put_contents($baseDir . DIRECTORY_SEPARATOR . 'idegui_demo_goldspiel.md', implode(PHP_EOL, $demo));
$index[] = '- `idegui_demo_goldspiel.md`';

file_put_contents($baseDir . DIRECTORY_SEPARATOR . 'INDEX.md', implode(PHP_EOL, $index));

$conn->close();
echo 'OK export to: ' . $baseDir . PHP_EOL;
?>