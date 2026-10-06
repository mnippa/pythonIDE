<?php
require __DIR__ . '/../config/database.php';

function infer_test_type($testCase) {
    if (!is_array($testCase)) {
        return null;
    }

    if (!empty($testCase['type']) && is_string($testCase['type'])) {
        return trim($testCase['type']);
    }
    if (isset($testCase['mode'])) {
        return 'intelligent';
    }
    if (isset($testCase['function_name']) || isset($testCase['test_cases'])) {
        return 'function';
    }
    if (isset($testCase['init_var_names']) || isset($testCase['expected_var_names']) || isset($testCase['init_vars']) || isset($testCase['expected_vars'])) {
        return 'variable';
    }
    if (isset($testCase['expected']) || isset($testCase['expected_type']) || isset($testCase['validation_mode'])) {
        return 'output';
    }
    if (isset($testCase['keywords']) || isset($testCase['forbidden'])) {
        return 'code_check';
    }

    return null;
}

function parse_test_cases($raw) {
    if ($raw === null || trim((string)$raw) === '') {
        return [];
    }

    $parsed = json_decode((string)$raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    if (is_string($parsed)) {
        $parsed2 = json_decode($parsed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $parsed = $parsed2;
        }
    }

    if (is_array($parsed)) {
        if (isset($parsed[0]) || empty($parsed)) {
            return $parsed;
        }
        return [$parsed];
    }

    return [];
}

function build_autodesc_section(array $testCasesData, $isManualReview) {
    $tableRows = '';
    $seenTypes = [];

    foreach ($testCasesData as $testCase) {
        if (!is_array($testCase)) {
            continue;
        }

        $type = infer_test_type($testCase);

        if ($type === 'function' && empty($seenTypes['function'])) {
            $seenTypes['function'] = true;

            $funcName = isset($testCase['function_name']) ? $testCase['function_name'] : 'Funktion';
            $paramCount = 0;
            if (isset($testCase['test_cases']) && is_array($testCase['test_cases']) && count($testCase['test_cases']) > 0) {
                $firstCase = $testCase['test_cases'][0];
                if (is_array($firstCase) && isset($firstCase['args']) && is_array($firstCase['args'])) {
                    $paramCount = count($firstCase['args']);
                }
            }

            $tableRows .= '<tr><td>Funktionsname</td><td>' . htmlspecialchars((string)$funcName, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            $tableRows .= '<tr><td>Parameter</td><td>' . (int)$paramCount . '</td></tr>';
        }

        if ($type === 'variable' && empty($seenTypes['variable'])) {
            $seenTypes['variable'] = true;

            $initVars = isset($testCase['init_var_names']) && is_array($testCase['init_var_names']) ? $testCase['init_var_names'] : [];
            $checkingVars = isset($testCase['expected_var_names']) && is_array($testCase['expected_var_names']) ? $testCase['expected_var_names'] : [];

            $tableRows .= '<tr><td>Input-Variablen</td><td>' . htmlspecialchars((count($initVars) ? implode(', ', $initVars) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
            $tableRows .= '<tr><td>Checking</td><td>' . htmlspecialchars((count($checkingVars) ? implode(', ', $checkingVars) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        if ($type === 'intelligent' && empty($seenTypes['intelligent'])) {
            $seenTypes['intelligent'] = true;

            $mode = isset($testCase['mode']) ? $testCase['mode'] : 'unknown';
            if ($mode === 'function' && isset($testCase['function']) && is_array($testCase['function'])) {
                $funcName = isset($testCase['function']['name']) ? $testCase['function']['name'] : 'Funktion';
                $params = isset($testCase['function']['params']) && is_array($testCase['function']['params']) ? $testCase['function']['params'] : [];
                $tableRows .= '<tr><td>Funktionsname</td><td>' . htmlspecialchars((string)$funcName, ENT_QUOTES, 'UTF-8') . '</td></tr>';
                $tableRows .= '<tr><td>Parameter</td><td>' . count($params) . '</td></tr>';
            } elseif ($mode === 'vars') {
                $inputs = isset($testCase['inputs']) && is_array($testCase['inputs']) ? $testCase['inputs'] : [];
                $checking = isset($testCase['outputs']) && is_array($testCase['outputs']) ? $testCase['outputs'] : [];
                $tableRows .= '<tr><td>INPUTS erwartet</td><td>' . count($inputs) . '</td></tr>';
                $tableRows .= '<tr><td>Input-Variablen</td><td>' . htmlspecialchars((count($inputs) ? implode(', ', $inputs) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                $tableRows .= '<tr><td>Checking</td><td>' . htmlspecialchars((count($checking) ? implode(', ', $checking) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
        }

        if ($type === 'output' && empty($seenTypes['output'])) {
            $seenTypes['output'] = true;

            $expectedType = isset($testCase['expected_type']) ? $testCase['expected_type'] : 'text';
            $validationMode = isset($testCase['validation_mode']) ? $testCase['validation_mode'] : 'default';

            $typeDescs = [
                'regex' => 'Regex Pattern',
                'solution' => 'Solution Code Output',
                'text' => 'Text Pattern'
            ];
            $validationModes = [
                'strict' => 'Exact Match',
                'loose' => 'Flexible Match',
                'contains' => 'Contains Check',
                'forbidden' => 'Pattern Forbidden'
            ];

            $typeDescription = isset($typeDescs[$expectedType]) ? $typeDescs[$expectedType] : $expectedType;
            $modeDescription = isset($validationModes[$validationMode]) ? $validationModes[$validationMode] : $validationMode;

            $outputDescription = $typeDescription;
            if ($expectedType === 'text' && $validationMode !== 'default') {
                $outputDescription = $modeDescription;
            }

            $tableRows .= '<tr><td>OUTPUT</td><td>' . htmlspecialchars((string)$outputDescription, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        if ($type === 'code_check' && empty($seenTypes['code_check'])) {
            $seenTypes['code_check'] = true;

            $keywords = isset($testCase['keywords']) && is_array($testCase['keywords']) ? $testCase['keywords'] : [];
            $forbidden = isset($testCase['forbidden']) && is_array($testCase['forbidden']) ? $testCase['forbidden'] : [];

            $tableRows .= '<tr><td>Erforderliche Keywords</td><td>' . htmlspecialchars((count($keywords) ? implode(', ', $keywords) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
            $tableRows .= '<tr><td>Verbotene Keywords</td><td>' . htmlspecialchars((count($forbidden) ? implode(', ', $forbidden) : 'keine'), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
    }

    if ($tableRows === '' && !$isManualReview) {
        return '';
    }

    $tableRows .= '<tr><td>Pruefung</td><td>' . ($isManualReview ? 'manuell durch Admin' : 'automatisch') . '</td></tr>';

    $section = '<div class="test-requirements-section"><h3>Test-Anforderungen</h3>';
    $section .= '<table class="test-requirements-table"><thead><tr><th>Aspekt</th><th>Details</th></tr></thead><tbody>';
    $section .= $tableRows;
    $section .= '</tbody></table></div>';

    return $section;
}

$conn = getDbConnection();
$sql = "SELECT id, assignment_id, description, test_cases, manual_review_required FROM tasks WHERE assignment_id IN (36, 37) ORDER BY assignment_id, position";
$res = $conn->query($sql);
if (!$res) {
    fwrite(STDERR, "Query failed: " . $conn->error . PHP_EOL);
    exit(1);
}

$updated = 0;
while ($row = $res->fetch_assoc()) {
    $taskId = (int)$row['id'];
    $manualReview = (int)$row['manual_review_required'] === 1;
    $testCasesData = parse_test_cases($row['test_cases']);

    $newSection = build_autodesc_section($testCasesData, $manualReview);

    $currentDescription = (string)($row['description'] ?? '');
    $baseDescription = preg_replace('~<div class="test-requirements-section">.*?</div>\s*~is', '', $currentDescription);
    $baseDescription = rtrim((string)$baseDescription);

    $newDescription = $baseDescription;
    if ($newSection !== '') {
        $newDescription .= $newSection;
    }

    if ($newDescription === $currentDescription) {
        continue;
    }

    $stmt = $conn->prepare('UPDATE tasks SET description = ? WHERE id = ?');
    if (!$stmt) {
        fwrite(STDERR, "Prepare failed for task {$taskId}: " . $conn->error . PHP_EOL);
        continue;
    }
    $stmt->bind_param('si', $newDescription, $taskId);
    if (!$stmt->execute()) {
        fwrite(STDERR, "Update failed for task {$taskId}: " . $stmt->error . PHP_EOL);
        $stmt->close();
        continue;
    }

    $stmt->close();
    $updated++;
    echo "Updated task {$taskId}" . PHP_EOL;
}

echo "Done. Updated {$updated} tasks." . PHP_EOL;
