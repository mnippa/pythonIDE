<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

$task1Title = 'RC BFS Goldspiel: Muenzen und Ziel';
$task2Title = 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel';

$task1Template = <<<'PYTHON'
# Aufgabe:
# 1) Berechne die kuerzeste Schrittzahl, um ALLE Muenzen einzusammeln und danach das Ziel zu erreichen.
# 2) Berechne die kuerzeste Schrittzahl von Start direkt zum Ziel (ohne Muenzpflicht).
# Antwortformat: collect_then_goal;direct_goal

# Gegebene Werte:
# - board_lines: {board_lines}
# - start: {start}
# - goal: {goal}
# - coins: {coins}

# Deine finale Antwort muss als String in answer stehen, z.B. "23;11".
answer = ""
PYTHON;

$task2Template = <<<'PYTHON'
# Aufgabe:
# 1) Berechne den kuerzesten Weg von Start zu Ziel mit BFS.
# 2) Berechne die Schrittzahl mit rechter-Hand-Regel.
# Antwortformat: shortest_bfs;right_hand_steps

# Gegebene Werte:
# - board_lines: {board_lines}
# - start: {start}
# - goal: {goal}

# Deine finale Antwort muss als String in answer stehen, z.B. "17;29".
answer = ""
PYTHON;

$task1Overrides = json_encode([
    [
        'inputs' => [
            'board_lines' => '<random>',
            'start' => '<random>',
            'goal' => '<random>',
            'coins' => '<random>'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_SLASHES);

$task2Overrides = json_encode([
    [
        'inputs' => [
            'board_lines' => '<random>',
            'start' => '<random>',
            'goal' => '<random>'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_SLASHES);

$taskType = 'code_random_complex';
$problemType = 'code_completion';
$iterations = 3;
$maxAttempts = 6;
$showSolution = 1;
$showSolutionCode = 1;
$folderstructure = 0;
$allowDownload = 0;
$allowCodeUiWebEdit = 1;
$difficulty = 'hard';

$updates = [
    $task1Title => [
        'code_template' => $task1Template,
    ],
    $task2Title => [
        'code_template' => $task2Template,
    ],
];

$stmt = $conn->prepare('SELECT id FROM tasks WHERE title = ? LIMIT 1');
$upd = $conn->prepare('UPDATE tasks SET code_template = ?, iterations_count = ?, max_attempts = ?, updated_at = NOW() WHERE id = ?');

foreach ($updates as $title => $data) {
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        echo "SKIP missing task: $title\n";
        continue;
    }

    $taskId = (int)$row['id'];
    $upd->bind_param(
        'siii',
        $data['code_template'],
        $iterations,
        $maxAttempts,
        $taskId
    );

    if (!$upd->execute()) {
        echo "ERROR updating $taskId ($title): {$upd->error}\n";
        exit(1);
    }

    echo "Updated task $taskId ($title)\n";
}

$upd->close();
$stmt->close();
$conn->close();
