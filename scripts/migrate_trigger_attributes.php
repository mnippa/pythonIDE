<?php
/**
 * Migrate idegui trigger attributes to unified standard.
 *
 * Standard:
 * - Full run: data-run="true"
 * - Function call: data-function="functionName"
 * - Trigger metadata: name / value
 *
 * Legacy compatibility migration:
 * - data-run-python="true" -> data-run="true"
 * - data-run-name -> data-function (preferred) + name/value
 * - data-run-value -> value
 *
 * Sources:
 * 1) project_files table (index.html)
 * 2) user_task_files table (index.html overrides)
 * 3) storage/tasks/folders/task_<id>/index.html
 *
 * Usage:
 *   php scripts/migrate_trigger_attributes.php --dry-run
 *   php scripts/migrate_trigger_attributes.php --apply
 */

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run via CLI.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;

$conn = getDbConnection();
$conn->set_charset('utf8mb4');

function parseAttributes(string $attrString): array {
    $attrs = [];
    if (!preg_match_all('/([^\s=\/>]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>=]+))?/u', $attrString, $matches, PREG_SET_ORDER)) {
        return $attrs;
    }

    foreach ($matches as $m) {
        $name = $m[1];
        $rawValue = $m[2] ?? null;
        $value = null;
        if ($rawValue !== null) {
            $first = substr($rawValue, 0, 1);
            $last = substr($rawValue, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($rawValue, 1, -1);
            } else {
                $value = $rawValue;
            }
        }

        $attrs[] = [
            'name' => $name,
            'value' => $value,
        ];
    }

    return $attrs;
}

function findAttrIndex(array $attrs, string $name): int {
    foreach ($attrs as $i => $attr) {
        if (strcasecmp($attr['name'], $name) === 0) {
            return $i;
        }
    }
    return -1;
}

function getAttr(array $attrs, string $name): ?string {
    $idx = findAttrIndex($attrs, $name);
    if ($idx === -1) return null;
    return $attrs[$idx]['value'];
}

function hasAttr(array $attrs, string $name): bool {
    return findAttrIndex($attrs, $name) !== -1;
}

function setAttr(array &$attrs, string $name, ?string $value): void {
    $idx = findAttrIndex($attrs, $name);
    if ($idx === -1) {
        $attrs[] = ['name' => $name, 'value' => $value];
        return;
    }
    $attrs[$idx]['value'] = $value;
}

function removeAttr(array &$attrs, string $name): void {
    $idx = findAttrIndex($attrs, $name);
    if ($idx === -1) return;
    array_splice($attrs, $idx, 1);
}

function serializeTag(string $tagName, array $attrs, bool $selfClosing): string {
    $parts = [];
    foreach ($attrs as $attr) {
        $name = $attr['name'];
        $value = $attr['value'];
        if ($value === null) {
            $parts[] = $name;
        } else {
            $safe = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $parts[] = $name . '="' . $safe . '"';
        }
    }

    $attrsOut = count($parts) ? ' ' . implode(' ', $parts) : '';
    return '<' . $tagName . $attrsOut . ($selfClosing ? ' />' : '>');
}

function migrateTag(string $fullTag, array &$stats): string {
    if (!preg_match('/^<(?!\/|!|\?)([a-zA-Z][\w:-]*)([^<>]*?)(\/?)>$/us', $fullTag, $m)) {
        return $fullTag;
    }

    $tagName = $m[1];
    $attrString = $m[2] ?? '';
    $selfClosing = trim((string)($m[3] ?? '')) === '/';

    if (
        stripos($attrString, 'data-run-python') === false &&
        stripos($attrString, 'data-run-name') === false &&
        stripos($attrString, 'data-run-value') === false &&
        stripos($attrString, 'data-function') === false &&
        stripos($attrString, 'data-run') === false
    ) {
        return $fullTag;
    }

    $attrs = parseAttributes($attrString);
    $before = serializeTag($tagName, $attrs, $selfClosing);

    $dataFunction = getAttr($attrs, 'data-function');
    $dataRunName = getAttr($attrs, 'data-run-name');
    $dataRunValue = getAttr($attrs, 'data-run-value');

    if (($dataFunction === null || $dataFunction === '') && $dataRunName !== null && $dataRunName !== '') {
        setAttr($attrs, 'data-function', $dataRunName);
        $stats['converted_run_name_to_function']++;
        $dataFunction = $dataRunName;
    }

    $isFunctionMode = ($dataFunction !== null && $dataFunction !== '');

    if ($isFunctionMode) {
        if (!hasAttr($attrs, 'name') || trim((string)getAttr($attrs, 'name')) === '') {
            setAttr($attrs, 'name', $dataFunction);
            $stats['filled_name']++;
        }

        $existingValue = getAttr($attrs, 'value');
        if ($existingValue === null || trim((string)$existingValue) === '') {
            if ($dataRunValue !== null && $dataRunValue !== '') {
                setAttr($attrs, 'value', $dataRunValue);
            } else {
                setAttr($attrs, 'value', $dataFunction);
            }
            $stats['filled_value']++;
        }

        if (hasAttr($attrs, 'data-run-python')) {
            removeAttr($attrs, 'data-run-python');
            $stats['removed_data_run_python']++;
        }
        if (hasAttr($attrs, 'data-run')) {
            removeAttr($attrs, 'data-run');
            $stats['removed_data_run']++;
        }
        if (hasAttr($attrs, 'data-run-name')) {
            removeAttr($attrs, 'data-run-name');
            $stats['removed_data_run_name']++;
        }
        if (hasAttr($attrs, 'data-run-value')) {
            removeAttr($attrs, 'data-run-value');
            $stats['removed_data_run_value']++;
        }
    } else {
        if (hasAttr($attrs, 'data-run-python')) {
            removeAttr($attrs, 'data-run-python');
            if (!hasAttr($attrs, 'data-run')) {
                setAttr($attrs, 'data-run', 'true');
            }
            $stats['converted_run_python_to_run']++;
        }

        if ($dataRunName !== null && $dataRunName !== '') {
            if (!hasAttr($attrs, 'name') || trim((string)getAttr($attrs, 'name')) === '') {
                setAttr($attrs, 'name', $dataRunName);
                $stats['filled_name']++;
            }
            if (!hasAttr($attrs, 'value') || trim((string)getAttr($attrs, 'value')) === '') {
                setAttr($attrs, 'value', $dataRunName);
                $stats['filled_value']++;
            }
            removeAttr($attrs, 'data-run-name');
            $stats['removed_data_run_name']++;
        }

        if ($dataRunValue !== null) {
            if (!hasAttr($attrs, 'value') || trim((string)getAttr($attrs, 'value')) === '') {
                setAttr($attrs, 'value', $dataRunValue);
                $stats['filled_value']++;
            }
            removeAttr($attrs, 'data-run-value');
            $stats['removed_data_run_value']++;
        }
    }

    $after = serializeTag($tagName, $attrs, $selfClosing);
    if ($after !== $before) {
        $stats['tags_changed']++;
        return $after;
    }

    return $fullTag;
}

function migrateHtmlContent(string $html, array &$stats): string {
    return preg_replace_callback('/<(?!\/|!|\?)[a-zA-Z][\w:-]*[^<>]*>/us', function ($m) use (&$stats) {
        return migrateTag($m[0], $stats);
    }, $html);
}

function tableExists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

$stats = [
    'files_scanned' => 0,
    'files_changed' => 0,
    'tags_changed' => 0,
    'converted_run_name_to_function' => 0,
    'converted_run_python_to_run' => 0,
    'removed_data_run_python' => 0,
    'removed_data_run' => 0,
    'removed_data_run_name' => 0,
    'removed_data_run_value' => 0,
    'filled_name' => 0,
    'filled_value' => 0,
    'db_project_rows_changed' => 0,
    'db_user_override_rows_changed' => 0,
    'task_files_changed' => 0,
    'task_backups_written' => 0,
];

// 1) Project files
if (tableExists($conn, 'project_files')) {
    $sql = "SELECT id, content FROM project_files WHERE LOWER(name) = 'index.html'";
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        $update = $conn->prepare("UPDATE project_files SET content = ?, file_size = ?, updated_at = NOW() WHERE id = ?");
        while ($row = $res->fetch_assoc()) {
            $stats['files_scanned']++;
            $old = (string)$row['content'];
            $new = migrateHtmlContent($old, $stats);
            if ($new !== $old) {
                $stats['files_changed']++;
                $stats['db_project_rows_changed']++;
                if ($apply) {
                    $size = strlen($new);
                    $id = (int)$row['id'];
                    $update->bind_param('sii', $new, $size, $id);
                    $update->execute();
                }
            }
        }
        if ($update) {
            $update->close();
        }
        $res->close();
    }
}

// 2) User task overrides
if (tableExists($conn, 'user_task_files')) {
    $sql = "SELECT user_id, task_id, file_path, content FROM user_task_files WHERE LOWER(file_path) LIKE '%index.html'";
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        $update = $conn->prepare("UPDATE user_task_files SET content = ?, updated_at = NOW() WHERE user_id = ? AND task_id = ? AND file_path = ?");
        while ($row = $res->fetch_assoc()) {
            $stats['files_scanned']++;
            $old = (string)$row['content'];
            $new = migrateHtmlContent($old, $stats);
            if ($new !== $old) {
                $stats['files_changed']++;
                $stats['db_user_override_rows_changed']++;
                if ($apply) {
                    $userId = (int)$row['user_id'];
                    $taskId = (int)$row['task_id'];
                    $filePath = (string)$row['file_path'];
                    $update->bind_param('siis', $new, $userId, $taskId, $filePath);
                    $update->execute();
                }
            }
        }
        if ($update) {
            $update->close();
        }
        $res->close();
    }
}

// 3) Task folder files
$taskIndexFiles = glob(__DIR__ . '/../storage/tasks/folders/task_*/index.html') ?: [];
foreach ($taskIndexFiles as $path) {
    $content = @file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $stats['files_scanned']++;
    $new = migrateHtmlContent($content, $stats);
    if ($new !== $content) {
        $stats['files_changed']++;
        $stats['task_files_changed']++;
        if ($apply) {
            $backupPath = $path . '.pre-trigger-migration.bak';
            if (!file_exists($backupPath)) {
                if (@file_put_contents($backupPath, $content) !== false) {
                    $stats['task_backups_written']++;
                }
            }
            @file_put_contents($path, $new);
        }
    }
}

echo "\n=== Trigger Attribute Migration ===\n";
echo "Mode: " . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

echo "Scanned files: {$stats['files_scanned']}\n";
echo "Changed files: {$stats['files_changed']}\n";
echo "  - DB project_files rows: {$stats['db_project_rows_changed']}\n";
echo "  - DB user_task_files rows: {$stats['db_user_override_rows_changed']}\n";
echo "  - Task folder index.html files: {$stats['task_files_changed']}\n";
if ($apply) {
    echo "  - Backups written: {$stats['task_backups_written']}\n";
}

echo "\nTag-level changes:\n";
echo "  - Tags changed: {$stats['tags_changed']}\n";
echo "  - Converted data-run-name -> data-function: {$stats['converted_run_name_to_function']}\n";
echo "  - Converted data-run-python -> data-run: {$stats['converted_run_python_to_run']}\n";
echo "  - Removed data-run-python: {$stats['removed_data_run_python']}\n";
echo "  - Removed data-run: {$stats['removed_data_run']}\n";
echo "  - Removed data-run-name: {$stats['removed_data_run_name']}\n";
echo "  - Removed data-run-value: {$stats['removed_data_run_value']}\n";
echo "  - Filled name attrs: {$stats['filled_name']}\n";
echo "  - Filled value attrs: {$stats['filled_value']}\n";

echo "\n";
if ($dryRun) {
    echo "Run with --apply to persist changes.\n";
} else {
    echo "Migration applied successfully.\n";
}
