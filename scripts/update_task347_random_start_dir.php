<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

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

$task2Overrides = json_encode([
    [
        'inputs' => [
            'board_lines' => '<random>',
            'start' => '<random>',
            'goal' => '<random>',
            'start_dir' => '<random>'
        ],
        'expected' => [
            'variable' => 'result'
        ]
    ]
], JSON_UNESCAPED_SLASHES);

$stmt = $conn->prepare('UPDATE tasks SET code_template = ?, solution_code = ?, randomizer_code = ?, variable_overrides = ?, show_solution_code = 0, iterations_count = 3, max_attempts = 6, updated_at = NOW() WHERE id = 347');
if (!$stmt) {
    echo 'Prepare failed: ' . $conn->error . PHP_EOL;
    exit(1);
}

$stmt->bind_param('ssss', $task2Template, $task2Solution, $task2Randomizer, $task2Overrides);
if (!$stmt->execute()) {
    echo 'Update failed: ' . $stmt->error . PHP_EOL;
    exit(1);
}
$stmt->close();

echo "Task 347 updated with random start_dir and aligned template/solution/overrides\n";
