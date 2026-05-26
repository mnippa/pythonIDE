<?php
/**
 * Create 2 code_random_complex tasks in Assignment F (Spiele und UI)
 *
 * - Task 1: Goldspiel coins + goal (BFS)
 * - Task 2: Labyrinth BFS vs right-hand-rule
 *
 * Run:
 *   php scripts/create_assignment_f_bfs_random_complex.php
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

function findAssignmentId(mysqli $conn): ?int {
    $candidates = [
        'F) Spiele und UI',
        'F Spiele und UI',
        'F Goldspiel Labyrinth GUI'
    ];

    foreach ($candidates as $title) {
        $stmt = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('s', $title);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }
    }

    // Fallback: try broad match for assignment F naming.
    $sql = "SELECT id FROM assignments WHERE title LIKE 'F)%' OR title LIKE 'F %' ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        return (int)$row['id'];
    }

    return null;
}

$assignmentId = findAssignmentId($conn);
if (!$assignmentId) {
    echo "ERROR: Could not find Assignment F.\n";
    exit(1);
}

$stmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
$stmt->bind_param('i', $assignmentId);
$stmt->execute();
$nextPosRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nextPos = (int)($nextPosRow['next_pos'] ?? 1);

$task1Title = 'RC BFS Goldspiel: Muenzen und Ziel';
$task2Title = 'RC BFS Labyrinth: kuerzester Weg vs Rechtsregel';

$deleteStmt = $conn->prepare('DELETE FROM tasks WHERE assignment_id = ? AND title IN (?, ?)');
$deleteStmt->bind_param('iss', $assignmentId, $task1Title, $task2Title);
$deleteStmt->execute();
$deleted = $deleteStmt->affected_rows;
$deleteStmt->close();

$task1Randomizer = <<<'PYTHON'
import random
from collections import deque

BASE = [
    "#########",
    "#.......#",
    "#..#....#",
    "#..#....#",
    "#.......#",
    "#....#..#",
    "#....#..#",
    "#.......#",
    "#########",
]

H = len(BASE)
W = len(BASE[0])


def free_cells():
    out = []
    for y in range(H):
        for x in range(W):
            if BASE[y][x] == ".":
                out.append((x, y))
    return out


def dist(a, b):
    q = deque([(a[0], a[1], 0)])
    seen = {a}
    while q:
        x, y, d = q.popleft()
        if (x, y) == b:
            return d
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if BASE[ny][nx] == "#":
                continue
            if (nx, ny) in seen:
                continue
            seen.add((nx, ny))
            q.append((nx, ny, d + 1))
    return None


cells = free_cells()

for _ in range(3000):
    picks = random.sample(cells, 6)
    s = picks[0]
    g = picks[1]
    c = picks[2:6]

    ok = dist(s, g) is not None
    if ok:
        for coin in c:
            if dist(s, coin) is None:
                ok = False
                break

    if ok:
        start = {"x": s[0], "y": s[1]}
        goal = {"x": g[0], "y": g[1]}
        coins = [{"x": p[0], "y": p[1]} for p in c]

        chars = [list(r) for r in BASE]
        chars[s[1]][s[0]] = "S"
        chars[g[1]][g[0]] = "G"
        for p in c:
            chars[p[1]][p[0]] = "C"
        board_lines = ["".join(r) for r in chars]
        break
else:
    start = {"x": 1, "y": 1}
    goal = {"x": 7, "y": 7}
    coins = [{"x": 2, "y": 1}, {"x": 3, "y": 1}, {"x": 4, "y": 1}, {"x": 5, "y": 1}]
    board_lines = BASE
PYTHON;

$task1Solution = <<<'PYTHON'
from collections import deque

grid = [list(r) for r in board_lines]
H = len(grid)
W = len(grid[0])

S = (start["x"], start["y"])
G = (goal["x"], goal["y"])
coin_pos = [(c["x"], c["y"]) for c in coins]
coin_idx = {p: i for i, p in enumerate(coin_pos)}
all_mask = (1 << len(coin_pos)) - 1


def is_wall(x, y):
    return grid[y][x] == "#"


def bfs_direct():
    q = deque([(S[0], S[1], 0)])
    seen = {S}
    while q:
        x, y, d = q.popleft()
        if (x, y) == G:
            return d
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if is_wall(nx, ny):
                continue
            if (nx, ny) in seen:
                continue
            seen.add((nx, ny))
            q.append((nx, ny, d + 1))
    return -1


def bfs_collect_then_goal():
    start_mask = 0
    if S in coin_idx:
        start_mask |= 1 << coin_idx[S]

    q = deque([(S[0], S[1], start_mask, 0)])
    seen = {(S[0], S[1], start_mask)}

    while q:
        x, y, mask, d = q.popleft()
        if (x, y) == G and mask == all_mask:
            return d

        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if is_wall(nx, ny):
                continue

            nmask = mask
            if (nx, ny) in coin_idx:
                nmask |= 1 << coin_idx[(nx, ny)]

            st = (nx, ny, nmask)
            if st in seen:
                continue
            seen.add(st)
            q.append((nx, ny, nmask, d + 1))
    return -1


collect_then_goal = bfs_collect_then_goal()
direct_goal = bfs_direct()
result = str(collect_then_goal) + ";" + str(direct_goal)
PYTHON;

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

$task2Randomizer = <<<'PYTHON'
import random
from collections import deque

BASE = [
    "###########",
    "#.....#...#",
    "#.###.#.#.#",
    "#...#...#.#",
    "###.#####.#",
    "#...#.....#",
    "#.#.#.###.#",
    "#.#...#...#",
    "#.#####.#.#",
    "#.......#.#",
    "###########",
]

H = len(BASE)
W = len(BASE[0])


def free_cells():
    out = []
    for y in range(H):
        for x in range(W):
            if BASE[y][x] == ".":
                out.append((x, y))
    return out


def dist(a, b):
    q = deque([(a[0], a[1], 0)])
    seen = {a}
    while q:
        x, y, d = q.popleft()
        if (x, y) == b:
            return d
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if BASE[ny][nx] == "#":
                continue
            if (nx, ny) in seen:
                continue
            seen.add((nx, ny))
            q.append((nx, ny, d + 1))
    return None


def right_hand_steps(start_xy, goal_xy, start_dir_name):
    dirs = [(1, 0), (0, 1), (-1, 0), (0, -1)]
    dir_to_index = {
        "east": 0,
        "south": 1,
        "west": 2,
        "north": 3,
    }
    d = dir_to_index.get(start_dir_name, 0)
    x, y = start_xy
    steps = 0
    seen_states = set()
    cap = W * H * 30

    for _ in range(cap):
        if (x, y) == goal_xy:
            return steps

        state = (x, y, d)
        if state in seen_states:
            return -1
        seen_states.add(state)

        order = [(d + 1) % 4, d, (d + 3) % 4, (d + 2) % 4]
        moved = False
        for nd in order:
            nx = x + dirs[nd][0]
            ny = y + dirs[nd][1]
            if BASE[ny][nx] == "#":
                continue
            x, y = nx, ny
            d = nd
            steps += 1
            moved = True
            break

        if not moved:
            return -1

    return -1


def all_dirs_reach_goal(start_xy, goal_xy, dir_names):
    for dn in dir_names:
        if right_hand_steps(start_xy, goal_xy, dn) < 0:
            return False
    return True


cells = free_cells()
dir_names = ["east", "south", "west", "north"]
start_dir = "east"

for _ in range(3000):
    s, g = random.sample(cells, 2)
    trial_dir = random.choice(dir_names)
    if s == g:
        continue
    if dist(s, g) is None:
        continue
    if not all_dirs_reach_goal(s, g, dir_names):
        continue

    start = {"x": s[0], "y": s[1]}
    goal = {"x": g[0], "y": g[1]}
    start_dir = trial_dir

    chars = [list(r) for r in BASE]
    chars[s[1]][s[0]] = "S"
    chars[g[1]][g[0]] = "G"
    board_lines = ["".join(r) for r in chars]
    break
else:
    start = {"x": 1, "y": 1}
    goal = {"x": 8, "y": 9}
    start_dir = "east"
    chars = [list(r) for r in BASE]
    chars[start["y"]][start["x"]] = "S"
    chars[goal["y"]][goal["x"]] = "G"
    board_lines = ["".join(r) for r in chars]
PYTHON;

$task2Solution = <<<'PYTHON'
from collections import deque

grid = [list(r) for r in board_lines]
H = len(grid)
W = len(grid[0])

S = (start["x"], start["y"])
G = (goal["x"], goal["y"])

DIR_TO_INDEX = {
    "east": 0,
    "south": 1,
    "west": 2,
    "north": 3,
}

start_dir_index = DIR_TO_INDEX.get(start_dir, 0)


def is_wall(x, y):
    return grid[y][x] == "#"


def bfs_shortest():
    q = deque([(S[0], S[1], 0)])
    seen = {S}
    while q:
        x, y, d = q.popleft()
        if (x, y) == G:
            return d
        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if is_wall(nx, ny):
                continue
            if (nx, ny) in seen:
                continue
            seen.add((nx, ny))
            q.append((nx, ny, d + 1))
    return -1


def right_hand_steps():
    dirs = [(1, 0), (0, 1), (-1, 0), (0, -1)]
    d = start_dir_index
    x, y = S
    steps = 0
    cap = W * H * 20

    for _ in range(cap):
        if (x, y) == G:
            return steps

        order = [(d + 1) % 4, d, (d + 3) % 4, (d + 2) % 4]
        moved = False
        for nd in order:
            nx = x + dirs[nd][0]
            ny = y + dirs[nd][1]
            if is_wall(nx, ny):
                continue
            x, y = nx, ny
            d = nd
            steps += 1
            moved = True
            break

        if not moved:
            return -1

    return -1


shortest_bfs = bfs_shortest()
right_hand = right_hand_steps()
result = str(shortest_bfs) + ";" + str(right_hand)
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
# - start_dir: {start_dir}

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
        ,
            'start_dir' => '<random>'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_SLASHES);

$insertSql = 'INSERT INTO tasks (
    assignment_id,
    title,
    description,
    task_text,
    question_text,
    task_type,
    problem_type,
    position,
    code_template,
    solution_code,
    randomizer_code,
    variable_overrides,
    correct_answer,
    iterations_count,
    max_attempts,
    show_solution,
    show_solution_code,
    folderstructure,
    allowDownload,
    allow_code_ui_web_edit,
    task_difficulty,
    created_at,
    updated_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
)';

$insertStmt = $conn->prepare($insertSql);
if (!$insertStmt) {
    echo "ERROR prepare failed: " . $conn->error . "\n";
    exit(1);
}

$created = [];

$taskType = 'code_random_complex';
$problemType = 'code_completion';
$maxAttempts = 6;
$iterations = 3;
$showSolution = 1;
$showSolutionCode = 0;
$folderstructure = 0;
$allowDownload = 0;
$allowCodeUiWebEdit = 1;
$difficulty = 'hard';

$title = $task1Title;
$desc = 'BFS auf Zustandsraum: alle 4 Muenzen einsammeln und dann Ziel erreichen. Antwort als a;b.';
$taskText = 'Berechne zwei Werte und gib sie als "a;b" an: (1) kuerzeste Schritte fuer alle Muenzen + Ziel, (2) kuerzeste Schritte Start->Ziel ohne Muenzpflicht.';
$question = 'Antwortformat: collect_then_goal;direct_goal';
$position = $nextPos;
$template = $task1Template;
$solution = $task1Solution;
$randomizer = $task1Randomizer;
$overrides = $task1Overrides;
$correct = 'result';

$insertStmt->bind_param(
    'issssssisssssiiiiiiss',
    $assignmentId,
    $title,
    $desc,
    $taskText,
    $question,
    $taskType,
    $problemType,
    $position,
    $template,
    $solution,
    $randomizer,
    $overrides,
    $correct,
    $iterations,
    $maxAttempts,
    $showSolution,
    $showSolutionCode,
    $folderstructure,
    $allowDownload,
    $allowCodeUiWebEdit,
    $difficulty
);

if (!$insertStmt->execute()) {
    echo "ERROR creating task 1: " . $insertStmt->error . "\n";
    exit(1);
}
$created[] = ['id' => $conn->insert_id, 'title' => $title];

$title = $task2Title;
$desc = 'Vergleich von kuerzestem Weg (BFS) und rechter-Hand-Regel in einem Labyrinth. Antwort als a;b.';
$taskText = 'Berechne zwei Werte und gib sie als "a;b" an: (1) kuerzeste BFS-Schritte Start->Ziel, (2) Schritte mit rechter-Hand-Regel.';
$question = 'Antwortformat: shortest_bfs;right_hand_steps';
$position = $nextPos + 1;
$template = $task2Template;
$solution = $task2Solution;
$randomizer = $task2Randomizer;
$overrides = $task2Overrides;
$correct = 'result';

$insertStmt->bind_param(
    'issssssisssssiiiiiiss',
    $assignmentId,
    $title,
    $desc,
    $taskText,
    $question,
    $taskType,
    $problemType,
    $position,
    $template,
    $solution,
    $randomizer,
    $overrides,
    $correct,
    $iterations,
    $maxAttempts,
    $showSolution,
    $showSolutionCode,
    $folderstructure,
    $allowDownload,
    $allowCodeUiWebEdit,
    $difficulty
);

if (!$insertStmt->execute()) {
    echo "ERROR creating task 2: " . $insertStmt->error . "\n";
    exit(1);
}
$created[] = ['id' => $conn->insert_id, 'title' => $title];

$insertStmt->close();

echo "Assignment ID: {$assignmentId}\n";
echo "Deleted existing tasks with same titles: {$deleted}\n";
foreach ($created as $row) {
    echo "Created task {$row['id']}: {$row['title']}\n";
}

echo "Done.\n";
