<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

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
    "#.......#G#",
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


def find_goal():
    for y in range(H):
        for x in range(W):
            if BASE[y][x] == "G":
                return (x, y)
    return (W - 2, H - 2)


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


def right_hand_steps(start_xy, goal_xy):
    dirs = [(1, 0), (0, 1), (-1, 0), (0, -1)]
    d = 0
    x, y = start_xy
    steps = 0
    cap = W * H * 20

    for _ in range(cap):
        if (x, y) == goal_xy:
            return steps

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


goal_xy = find_goal()
cells = free_cells()
start_dir = "east"

for _ in range(3000):
    s = random.choice(cells)
    if s == goal_xy:
        continue
    if dist(s, goal_xy) is None:
        continue
    if right_hand_steps(s, goal_xy) < 0:
        continue

    start = {"x": s[0], "y": s[1]}
    goal = {"x": goal_xy[0], "y": goal_xy[1]}
    start_dir = "east"

    chars = [list(r) for r in BASE]
    chars[s[1]][s[0]] = "S"
    board_lines = ["".join(r) for r in chars]
    break
else:
    start = {"x": 1, "y": 1}
    goal = {"x": goal_xy[0], "y": goal_xy[1]}
    start_dir = "east"
    board_lines = BASE
PYTHON;

$taskIds = [346, 347];
$stmt = $conn->prepare('UPDATE tasks SET show_solution_code = 0, iterations_count = 3, max_attempts = 6, updated_at = NOW() WHERE id = ?');
if (!$stmt) {
    echo 'Prepare failed: ' . $conn->error . PHP_EOL;
    exit(1);
}

foreach ($taskIds as $taskId) {
    $stmt->bind_param('i', $taskId);
    if (!$stmt->execute()) {
        echo 'Failed to update task ' . $taskId . ': ' . $stmt->error . PHP_EOL;
        exit(1);
    }
}
$stmt->close();

$stmt2 = $conn->prepare('UPDATE tasks SET randomizer_code = ?, updated_at = NOW() WHERE id = 347');
if (!$stmt2) {
    echo 'Prepare failed for randomizer update: ' . $conn->error . PHP_EOL;
    exit(1);
}
$stmt2->bind_param('s', $task2Randomizer);
if (!$stmt2->execute()) {
    echo 'Failed to update randomizer: ' . $stmt2->error . PHP_EOL;
    exit(1);
}
$stmt2->close();

echo "Updated tasks 346 and 347\n";
echo "show_solution_code=0, iterations_count=3, max_attempts=6\n";
echo "Task 347 randomizer updated to include start_dir\n";
