import random
from collections import deque

# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

WIDTH = 6
HEIGHT = 6
INNER_OBSTACLES = 3
COINS = 5


def _empty_board():
    board = [[0 for _ in range(WIDTH)] for _ in range(HEIGHT)]

    # Rand komplett umrandet
    for x in range(WIDTH):
        board[0][x] = 1
        board[HEIGHT - 1][x] = 1
    for y in range(HEIGHT):
        board[y][0] = 1
        board[y][WIDTH - 1] = 1

    return board


def _inner_cells():
    cells = []
    for y in range(1, HEIGHT - 1):
        for x in range(1, WIDTH - 1):
            cells.append((x, y))
    return cells


def _walkable(board, x, y):
    return board[y][x] != 1


def _has_path(board, start, goal):
    q = deque([start])
    seen = {start}

    while q:
        x, y = q.popleft()
        if (x, y) == goal:
            return True

        for dx, dy in ((1, 0), (-1, 0), (0, 1), (0, -1)):
            nx, ny = x + dx, y + dy
            if nx < 0 or nx >= WIDTH or ny < 0 or ny >= HEIGHT:
                continue
            if (nx, ny) in seen:
                continue
            if not _walkable(board, nx, ny):
                continue
            seen.add((nx, ny))
            q.append((nx, ny))

    return False


def _generate_board():
    inner = _inner_cells()

    for _ in range(300):
        board = _empty_board()

        start = random.choice(inner)
        rest = [p for p in inner if p != start]

        goal = random.choice(rest)
        rest = [p for p in rest if p != goal]

        obstacles = random.sample(rest, INNER_OBSTACLES)
        rest = [p for p in rest if p not in obstacles]

        coins = random.sample(rest, COINS)

        for (x, y) in obstacles:
            board[y][x] = 1
        for (x, y) in coins:
            board[y][x] = 7

        board[start[1]][start[0]] = 2
        board[goal[1]][goal[0]] = 9

        if _has_path(board, start, goal):
            return board

    raise RuntimeError('Kein gueltiges 6x6-Spielfeld erzeugbar.')


SPIELFELD = _generate_board()


def finde_startposition(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 2:
                return {'x': x, 'y': y}
    return {'x': 1, 'y': 1}


def count_initial_gold(spielfeld):
    return sum(1 for row in spielfeld for value in row if value == 7)