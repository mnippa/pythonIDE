<?php
// Patch exported ZIP tasks so code_random_complex templates satisfy create.php validation.

$downloads = rtrim(getenv('USERPROFILE') ?: '', '\\/') . DIRECTORY_SEPARATOR . 'Downloads';
$inputZip = $downloads . DIRECTORY_SEPARATOR . 'tasks_export_3_tasks.zip';
$outputZip = $downloads . DIRECTORY_SEPARATOR . 'tasks_export_3_tasks_fixed.zip';
$tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zip_patch_' . uniqid();

if (!file_exists($inputZip)) {
    fwrite(STDERR, "Input ZIP not found: $inputZip\n");
    exit(1);
}

if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
    fwrite(STDERR, "Failed to create temp dir: $tmpDir\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($inputZip) !== true) {
    fwrite(STDERR, "Failed to open ZIP: $inputZip\n");
    exit(1);
}
$zip->extractTo($tmpDir);
$zip->close();

$taskFiles = glob($tmpDir . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR . '*.json');
if (!$taskFiles) {
    fwrite(STDERR, "No task files found in ZIP\n");
    exit(1);
}

$patched = 0;
foreach ($taskFiles as $taskFile) {
    $raw = file_get_contents($taskFile);
    $task = json_decode($raw, true);
    if (!is_array($task)) {
        continue;
    }

    $title = (string)($task['title'] ?? '');
    $type = (string)($task['task_type'] ?? '');
    if ($type !== 'code_random_complex') {
        continue;
    }

    if (stripos($title, 'Goldspiel') !== false) {
        $task['code_template'] = "# Aufgabe:\n# 1) Berechne die kuerzeste Schrittzahl, um ALLE Muenzen einzusammeln und danach das Ziel zu erreichen.\n# 2) Berechne die kuerzeste Schrittzahl von Start direkt zum Ziel (ohne Muenzpflicht).\n# Antwortformat: collect_then_goal;direct_goal\n\n# Gegebene Werte:\n# - board_lines: {board_lines}\n# - start: {start}\n# - goal: {goal}\n# - coins: {coins}\n\n# Deine finale Antwort muss als String in answer stehen, z.B. \"23;11\".\nanswer = \"\"";
        $patched++;
    } elseif (stripos($title, 'Labyrinth') !== false) {
        $task['code_template'] = "# Aufgabe:\n# 1) Berechne den kuerzesten Weg von Start zu Ziel mit BFS.\n# 2) Berechne die Schrittzahl mit rechter-Hand-Regel.\n# Antwortformat: shortest_bfs;right_hand_steps\n\n# Gegebene Werte:\n# - board_lines: {board_lines}\n# - start: {start}\n# - goal: {goal}\n# - start_dir: {start_dir}\n\n# Deine finale Antwort muss als String in answer stehen, z.B. \"17;29\".\nanswer = \"\"";
        $patched++;
    }

    file_put_contents($taskFile, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

if (file_exists($outputZip)) {
    unlink($outputZip);
}

$out = new ZipArchive();
if ($out->open($outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Failed to create output ZIP: $outputZip\n");
    exit(1);
}

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $fullPath = $file->getRealPath();
    $localPath = substr($fullPath, strlen($tmpDir) + 1);
    $out->addFile($fullPath, str_replace('\\', '/', $localPath));
}

$out->close();

// Cleanup temp dir
$rit = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($rit as $f) {
    $path = $f->getRealPath();
    if ($f->isDir()) {
        rmdir($path);
    } else {
        unlink($path);
    }
}
rmdir($tmpDir);

echo "Patched tasks: $patched\n";
echo "Output ZIP: $outputZip\n";
