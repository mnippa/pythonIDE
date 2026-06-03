
# 0 = Gang
# 1 = Wand
# 2 = Start
# 9 = Ziel

WIDTH = 15
HEIGHT = 15

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 1, 0, 1, 0, 1, 1, 1, 0, 1, 1, 1, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 1, 0, 1],
    [1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 1, 0, 1],
    [1, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1],
    [1, 0, 0, 0, 9, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
]

START_POS = {'x': 1, 'y': 1}
GOAL_POS = {'x': 3, 'y': 11}

DIRS = [
    (1, 0),
    (0, 1),
    (-1, 0),
    (0, -1),
]


def _in_bounds(x, y):
    return 0 <= x < WIDTH and 0 <= y < HEIGHT


def _cell_is_open(board, x, y):
    if not _in_bounds(x, y):
        return False
    return board[y][x] != 1


def _simulate_right_hand(board, start, goal, start_dir=(1, 0), max_steps=300):
    dir_index = DIRS.index(start_dir)
    x, y = start

    steps = 0
    action_counts = {'straight': 0, 'left': 0, 'u_turn': 0, 'right': 0}
    passed_near_goal = False

    for _ in range(max_steps):
        if (x, y) == goal:
            break

        gx, gy = goal
        if abs(x - gx) + abs(y - gy) == 1:
            passed_near_goal = True

        right_idx = (dir_index + 1) % 4
        straight_idx = dir_index
        left_idx = (dir_index - 1) % 4
        back_idx = (dir_index + 2) % 4

        rx, ry = DIRS[right_idx]
        sx, sy = DIRS[straight_idx]
        lx, ly = DIRS[left_idx]
        bx, by = DIRS[back_idx]

        if _cell_is_open(board, x + rx, y + ry):
            dir_index = right_idx
            action_counts['right'] = action_counts['right'] + 1
        elif _cell_is_open(board, x + sx, y + sy):
            action_counts['straight'] = action_counts['straight'] + 1
        elif _cell_is_open(board, x + lx, y + ly):
            dir_index = left_idx
            action_counts['left'] = action_counts['left'] + 1
        else:
            dir_index = back_idx
            action_counts['u_turn'] = action_counts['u_turn'] + 1

        dx, dy = DIRS[dir_index]
        x = x + dx
        y = y + dy
        steps = steps + 1

    reached = (x, y) == goal

    return {
        'reached': reached,
        'steps': steps,
        'actions': action_counts,
        'passed_near_goal': passed_near_goal,
    }


ANALYSE = _simulate_right_hand(
    SPIELFELD,
    (START_POS['x'], START_POS['y']),
    (GOAL_POS['x'], GOAL_POS['y'])
)

if not ANALYSE['reached']:
    raise RuntimeError('Festes Labyrinth verletzt die Rechte-Hand-Regel-Anforderung.')

if ANALYSE['steps'] < 20 or ANALYSE['steps'] > 25:
    raise RuntimeError('Festes Labyrinth liegt ausserhalb des gewuenschten Schrittbereichs.')