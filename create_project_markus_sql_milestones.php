<?php
require_once 'config/database.php';

$conn = getDbConnection();

$sourceDir = 'C:/Users/markus.nippa/Downloads/UML_nach_Datenbank_Vorlesung_SQL_Material';
$projectName = 'UML nach Datenbank - 6 Zwischenstaende';
$projectDescription = 'Import der 6 SQL-Meilensteine (M1-M6) als getrennte Datenbanken fuer die Vorlesung.';

if (!is_dir($sourceDir)) {
    die("Source directory not found: {$sourceDir}\n");
}

function splitSqlTopLevelByComma(string $input): array {
    $parts = [];
    $current = '';
    $depth = 0;
    $inSingle = false;
    $len = strlen($input);

    for ($i = 0; $i < $len; $i++) {
        $ch = $input[$i];
        $next = ($i + 1 < $len) ? $input[$i + 1] : '';

        if ($ch === "'" && !$inSingle) {
            $inSingle = true;
            $current .= $ch;
            continue;
        }

        if ($ch === "'" && $inSingle) {
            if ($next === "'") {
                $current .= "''";
                $i++;
                continue;
            }
            $inSingle = false;
            $current .= $ch;
            continue;
        }

        if ($inSingle) {
            $current .= $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            $current .= $ch;
            continue;
        }

        if ($ch === ')') {
            if ($depth > 0) {
                $depth--;
            }
            $current .= $ch;
            continue;
        }

        if ($ch === ',' && $depth === 0) {
            $trim = trim($current);
            if ($trim !== '') {
                $parts[] = $trim;
            }
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    $trim = trim($current);
    if ($trim !== '') {
        $parts[] = $trim;
    }

    return $parts;
}

function parseInsertTuples(string $valuesSql): array {
    $tuples = [];
    $len = strlen($valuesSql);
    $inSingle = false;
    $depth = 0;
    $current = '';

    for ($i = 0; $i < $len; $i++) {
        $ch = $valuesSql[$i];
        $next = ($i + 1 < $len) ? $valuesSql[$i + 1] : '';

        if ($ch === "'" && !$inSingle) {
            $inSingle = true;
            $current .= $ch;
            continue;
        }

        if ($ch === "'" && $inSingle) {
            if ($next === "'") {
                $current .= "''";
                $i++;
                continue;
            }
            $inSingle = false;
            $current .= $ch;
            continue;
        }

        if ($inSingle) {
            $current .= $ch;
            continue;
        }

        if ($ch === '(') {
            $depth++;
            if ($depth === 1) {
                $current = '';
                continue;
            }
        }

        if ($ch === ')') {
            if ($depth === 1) {
                $tuples[] = trim($current);
                $current = '';
                $depth--;
                continue;
            }
            if ($depth > 1) {
                $depth--;
            }
        }

        if ($depth >= 1) {
            $current .= $ch;
        }
    }

    return $tuples;
}

function sqlLiteralToString(string $raw): string {
    $v = trim($raw);
    if ($v === '' || strtoupper($v) === 'NULL') {
        return '';
    }
    if (strlen($v) >= 2 && $v[0] === "'" && $v[strlen($v) - 1] === "'") {
        $inner = substr($v, 1, -1);
        $inner = str_replace("''", "'", $inner);
        $inner = str_replace("\\'", "'", $inner);
        return $inner;
    }
    return $v;
}

function mapSqlTypeToDbSmall(string $sqlType, bool $isPk): string {
    if ($isPk) {
        return 'AUTO';
    }
    $upper = strtoupper(trim($sqlType));
    if (preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|INTEGER|BIGINT)\b/', $upper)) {
        return 'INTEGER';
    }
    if (preg_match('/^(DECIMAL|NUMERIC|FLOAT|DOUBLE|REAL)\b/', $upper)) {
        return 'REAL';
    }
    if (preg_match('/^(DATETIME|TIMESTAMP)\b/', $upper)) {
        return 'DATETIME';
    }
    if (preg_match('/^DATE\b/', $upper)) {
        return 'DATE';
    }
    if (preg_match('/^(BOOL|BOOLEAN)\b/', $upper)) {
        return 'BOOLEAN';
    }
    return 'TEXT';
}

function normalizeIdentifier(string $name, string $fallback): string {
    $s = trim($name);
    if ($s === '') {
        return $fallback;
    }
    $s = preg_replace('/[^A-Za-z0-9_]/', '_', $s);
    if ($s === '') {
        return $fallback;
    }
    if (preg_match('/^[0-9]/', $s)) {
        $s = 'n_' . $s;
    }
    return $s;
}

function parseSqlToDatabaseModel(string $dbName, string $sqlContent): array {
    $tables = [];

    if (preg_match_all('/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*(?:ENGINE|;)/is', $sqlContent, $createMatches, PREG_SET_ORDER)) {
        foreach ($createMatches as $m) {
            $tableName = normalizeIdentifier($m[1], 'table_1');
            $body = $m[2];

            $defs = splitSqlTopLevelByComma($body);
            $pkCols = [];
            $fkCols = [];
            $colDefs = [];

            foreach ($defs as $def) {
                $trim = trim($def);
                if (preg_match('/^PRIMARY\s+KEY\s*\((.+)\)/i', $trim, $pkMatch)) {
                    if (preg_match_all('/`?([A-Za-z0-9_]+)`?/', $pkMatch[1], $pkColMatches)) {
                        foreach ($pkColMatches[1] as $c) {
                            $pkCols[strtolower($c)] = true;
                        }
                    }
                    continue;
                }

                if (preg_match('/FOREIGN\s+KEY\s*\(([^)]+)\)/i', $trim, $fkMatch)) {
                    if (preg_match_all('/`?([A-Za-z0-9_]+)`?/', $fkMatch[1], $fkColMatches)) {
                        foreach ($fkColMatches[1] as $c) {
                            $fkCols[strtolower($c)] = true;
                        }
                    }
                    continue;
                }

                if (preg_match('/^(CONSTRAINT|UNIQUE\s+KEY|KEY|INDEX)\b/i', $trim)) {
                    continue;
                }

                if (preg_match('/^`?([A-Za-z0-9_]+)`?\s+([A-Za-z0-9_]+(?:\([^)]*\))?)(.*)$/i', $trim, $colMatch)) {
                    $colName = normalizeIdentifier($colMatch[1], 'col');
                    $type = $colMatch[2];
                    $rest = $colMatch[3] ?? '';
                    $lowerName = strtolower($colName);
                    $isPk = isset($pkCols[$lowerName]) || stripos($rest, 'PRIMARY KEY') !== false;
                    $isFk = isset($fkCols[$lowerName]) || stripos($rest, 'REFERENCES') !== false;
                    $default = '';
                    if (preg_match('/\bDEFAULT\s+([^\s,]+)/i', $rest, $defaultMatch)) {
                        $default = sqlLiteralToString($defaultMatch[1]);
                    }

                    $colDefs[] = [
                        'name' => $colName,
                        'type' => mapSqlTypeToDbSmall($type, $isPk),
                        'pk' => $isPk,
                        'fk' => $isFk,
                        'default' => $default,
                    ];
                }
            }

            if (count($colDefs) === 0) {
                $colDefs[] = ['name' => 'id', 'type' => 'AUTO', 'pk' => true, 'fk' => false, 'default' => ''];
            }

            $tables[strtolower($tableName)] = [
                'name' => $tableName,
                'columns' => $colDefs,
                'rows' => [],
            ];
        }
    }

    if (preg_match_all('/INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?\s*(\(([^)]*)\))?\s*VALUES\s*(.+?);/is', $sqlContent, $insertMatches, PREG_SET_ORDER)) {
        foreach ($insertMatches as $m) {
            $tableName = normalizeIdentifier($m[1], 'table_1');
            $tableKey = strtolower($tableName);
            if (!isset($tables[$tableKey])) {
                continue;
            }

            $colListRaw = isset($m[3]) ? trim((string)$m[3]) : '';
            $valuePart = $m[4];
            $tuples = parseInsertTuples($valuePart);

            $tableCols = $tables[$tableKey]['columns'];
            $nonPkCols = [];
            foreach ($tableCols as $col) {
                if (empty($col['pk'])) {
                    $nonPkCols[] = $col['name'];
                }
            }

            if ($colListRaw !== '') {
                $insertCols = [];
                foreach (explode(',', $colListRaw) as $colNameRaw) {
                    $insertCols[] = normalizeIdentifier(str_replace('`', '', trim($colNameRaw)), 'col');
                }
            } else {
                $insertCols = [];
                foreach ($tableCols as $col) {
                    $insertCols[] = $col['name'];
                }
            }

            foreach ($tuples as $tuple) {
                $vals = splitSqlTopLevelByComma($tuple);
                $row = [];
                foreach ($nonPkCols as $npk) {
                    $row[$npk] = '';
                }

                $max = min(count($insertCols), count($vals));
                for ($i = 0; $i < $max; $i++) {
                    $colName = $insertCols[$i];
                    if (!array_key_exists($colName, $row)) {
                        continue;
                    }
                    $row[$colName] = sqlLiteralToString($vals[$i]);
                }

                if (count($row) > 0) {
                    $tables[$tableKey]['rows'][] = $row;
                }
            }
        }
    }

    return [
        'name' => $dbName,
        'tables' => array_values($tables),
    ];
}

$sqlFiles = glob($sourceDir . '/M*.sql');
if (!$sqlFiles) {
    die("No SQL files (M*.sql) found in {$sourceDir}\n");
}

natsort($sqlFiles);
$sqlFiles = array_values($sqlFiles);
if (count($sqlFiles) < 6) {
    echo "Warning: expected 6 files, found " . count($sqlFiles) . "\n";
}

$userResult = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE LOWER(first_name)='markus' AND LOWER(last_name) LIKE 'nippa%' ORDER BY id ASC LIMIT 1");
$user = $userResult ? $userResult->fetch_assoc() : null;
if (!$user) {
    $userResult = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE LOWER(email) LIKE 'markus.nippa@%' ORDER BY id ASC LIMIT 1");
    $user = $userResult ? $userResult->fetch_assoc() : null;
}
if (!$user) {
    die("Target user 'markus nippa' not found.\n");
}
$userId = (int)$user['id'];

$databases = [];
$combinedSql = [];
foreach ($sqlFiles as $sqlFile) {
    $baseName = pathinfo($sqlFile, PATHINFO_FILENAME);
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        echo "Skipping unreadable file: {$sqlFile}\n";
        continue;
    }
    $databases[] = parseSqlToDatabaseModel($baseName, $sql);
    $combinedSql[] = "-- ===== {$baseName} =====\n" . trim($sql) . "\n";
}

if (count($databases) === 0) {
    die("No databases parsed from SQL files.\n");
}

$dbModel = [
    'version' => 2,
    'activeDatabaseIndex' => 0,
    'databases' => $databases,
];

$modelJson = json_encode($dbModel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($modelJson === false) {
    die("Failed to encode db_model.json\n");
}

$exportSql = implode("\n", $combinedSql);

$projectStmt = $conn->prepare('INSERT INTO projects (user_id, name, description, code, project_type, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?, NULL)');
if (!$projectStmt) {
    die("Prepare project insert failed: " . $conn->error . "\n");
}
$emptyCode = '';
$projectType = 'db_small';
$visibility = 'private';
$projectStmt->bind_param('isssss', $userId, $projectName, $projectDescription, $emptyCode, $projectType, $visibility);
if (!$projectStmt->execute()) {
    die("Project insert failed: " . $projectStmt->error . "\n");
}
$projectId = (int)$conn->insert_id;
$projectStmt->close();

$folderStmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
if ($folderStmt) {
    $folderName = 'includes';
    $folderStmt->bind_param('is', $projectId, $folderName);
    $folderStmt->execute();
    $folderName = 'img';
    $folderStmt->bind_param('is', $projectId, $folderName);
    $folderStmt->execute();
    $folderStmt->close();
}

$fileStmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
if (!$fileStmt) {
    die("Prepare file insert failed: " . $conn->error . "\n");
}

$filesToCreate = [
    [
        'name' => 'init.py',
        'content' => "# DB Small Projekt\n# Importierte SQL-Zwischenstaende sind in db_model.json als 6 Datenbanken enthalten.\n",
        'mime' => 'text/x-python',
    ],
    [
        'name' => 'db_model.json',
        'content' => $modelJson,
        'mime' => 'application/json',
    ],
    [
        'name' => 'db_export.sql',
        'content' => $exportSql,
        'mime' => 'application/sql',
    ],
];

foreach ($sqlFiles as $sqlFile) {
    $name = basename($sqlFile);
    $content = file_get_contents($sqlFile);
    if ($content === false) {
        continue;
    }
    $filesToCreate[] = [
        'name' => $name,
        'content' => $content,
        'mime' => 'application/sql',
    ];
}

foreach ($filesToCreate as $f) {
    $name = $f['name'];
    $content = $f['content'];
    $mime = $f['mime'];
    $size = strlen($content);
    $fileStmt->bind_param('isssi', $projectId, $name, $content, $mime, $size);
    if (!$fileStmt->execute()) {
        echo "File insert failed for {$name}: " . $fileStmt->error . "\n";
    }
}
$fileStmt->close();

$updateUserStmt = $conn->prepare('UPDATE users SET last_opened_project_id = ? WHERE id = ?');
if ($updateUserStmt) {
    $updateUserStmt->bind_param('ii', $projectId, $userId);
    $updateUserStmt->execute();
    $updateUserStmt->close();
}

echo "Created project_id={$projectId}\n";
echo "Owner user_id={$userId} ({$user['first_name']} {$user['last_name']}, {$user['email']})\n";
echo "Imported databases=" . count($databases) . "\n";
echo "Source files=" . count($sqlFiles) . "\n";
