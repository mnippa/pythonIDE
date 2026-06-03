<?php
require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
    $assignmentId = 21;
    $title = 'Goldspiel Grundspiel: 6x6 Zufallsfeld';

    $taskText = '1:1 Goldspiel-Schritt-3 Basis mit 6x6 Zufallsfeld: Rand komplett Wand, 3 innere Hindernisse, 5 Muenzen, Start und Ziel. Bewege den Spieler mit l/r/Enter.';
    $description = 'Grundspiel ohne IDEGUI im Stil von Goldspiel Schritt 3. Symbole, Dreh-/Vorwaertslogik und Konsolenfluss entsprechen der Vorlage.';
    $stoff = 'Goldspiel Schritt 3 (Symbole, Richtung als Vektor, drehen/gehen) + Zufalls-Spielfeldgenerator 6x6.';

    $taskType = 'code';
    $folderstructure = 1;

    $mainPy = <<<'PY'
from spielfeld import SPIELFELD, finde_startposition, count_initial_gold
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, ist_ziel

start = finde_startposition(SPIELFELD)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
    'gold': 0,
}

target_gold = count_initial_gold(SPIELFELD)

while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler, target_gold)
    outputFlush()

    cmd = input('l / r / Enter / q: ').strip().lower()

    if cmd == 'q':
        print('Beendet.')
        break

    if cmd == 'l':
        drehe_links(spieler)
        spieler['turns'] = spieler['turns'] + 1
    elif cmd == 'r':
        drehe_rechts(spieler)
        spieler['turns'] = spieler['turns'] + 1
    else:
        moved = gehe_vorwaerts(SPIELFELD, spieler)
        if moved:
            spieler['steps'] = spieler['steps'] + 1

    if ist_ziel(SPIELFELD, spieler):
        outputClear()
        render(SPIELFELD, spieler)
        render_status(spieler, target_gold)
        if spieler['gold'] >= target_gold:
            print('Ziel erreicht und alle Muenzen gesammelt.')
            break
        print('Ziel erreicht, aber noch nicht alle Muenzen gesammelt.')
PY;

    $spielfeldPy = <<<'PY'
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
PY;

    $functionPy = <<<'PY'
SYMBOLE = {
    0: '⏹️',
    1: '⬛',
    2: '🟦',
    7: '🪙',
    9: '🚪',
}

PFEILE = {
    '10': '➡️',
    '-10': '⬅️',
    '0-1': '⬆️',
    '01': '⬇️',
    '00': '⏺️',
}


def richtungs_key(spieler):
    return str(spieler['vX']) + str(spieler['vY'])


def render(spielfeld, spieler):
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        ausgabe = ''
        for x in range(width):
            if x == spieler['posX'] and y == spieler['posY']:
                key = richtungs_key(spieler)
                ausgabe = ausgabe + PFEILE[key]
            else:
                wert = spielfeld[y][x]
                ausgabe = ausgabe + SYMBOLE[wert]
        print(ausgabe)


def naechste_position(spieler):
    return {
        'x': spieler['posX'] + spieler['vX'],
        'y': spieler['posY'] + spieler['vY'],
    }


def ist_wand(spielfeld, position):
    return spielfeld[position['y']][position['x']] == 1


def ist_ziel(spielfeld, spieler):
    return spielfeld[spieler['posY']][spieler['posX']] == 9


def sammle_gold_wenn_noetig(spielfeld, spieler):
    x = spieler['posX']
    y = spieler['posY']
    if spielfeld[y][x] == 7:
        spieler['gold'] = spieler['gold'] + 1
        spielfeld[y][x] = 0


def drehe_links(spieler):
    alt_vx = spieler['vX']
    alt_vy = spieler['vY']
    spieler['vX'] = alt_vy
    spieler['vY'] = -alt_vx


def drehe_rechts(spieler):
    alt_vx = spieler['vX']
    alt_vy = spieler['vY']
    spieler['vX'] = -alt_vy
    spieler['vY'] = alt_vx


def gehe_vorwaerts(spielfeld, spieler):
    pos = naechste_position(spieler)
    if ist_wand(spielfeld, pos):
        return False

    spieler['posX'] = pos['x']
    spieler['posY'] = pos['y']
    sammle_gold_wenn_noetig(spielfeld, spieler)
    return True


def render_status(spieler, target_gold):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + ' / ' + str(target_gold))
PY;

    $readmeMd = <<<'MD'
# Goldspiel Grundspiel 6x6

1:1 auf Goldspiel Schritt 3 aufgebaut (gleiche Symbolik und Steuerlogik), aber mit 6x6 Spielfeld:
- Rand komplett als Wand
- 3 verteilte innere Hindernisse
- 5 zufaellige Muenzen
- Start und Ziel

Steuerung:
- `l` links drehen
- `r` rechts drehen
- `Enter` vorwaerts
- `q` beenden
MD;

    $codeTemplate = $mainPy;
    $solutionCode = $mainPy;

    $findStmt = $conn->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    $findStmt->bind_param('is', $assignmentId, $title);
    $findStmt->execute();
    $existing = $findStmt->get_result()->fetch_assoc();
    $findStmt->close();

    if ($existing) {
        $taskId = (int)$existing['id'];
        $updateStmt = $conn->prepare('UPDATE tasks SET
            task_text = ?,
            description = ?,
            stoff = ?,
            task_type = ?,
            problem_type = ?,
            folderstructure = ?,
            allowDownload = ?,
            code_template = ?,
            solution_code = ?,
            max_attempts = ?,
            iterations_count = ?,
            show_solution = ?,
            show_solution_code = ?,
            manual_review_required = ?,
            updated_at = NOW()
            WHERE id = ?');

        $allowDownload = 1;
        $maxAttempts = 10;
        $iterationsCount = 1;
        $showSolution = 1;
        $showSolutionCode = 1;
        $manualReview = 0;

        $updateStmt->bind_param(
            'sssssiissiiiiii',
            $taskText,
            $description,
            $stoff,
            $taskType,
            $problemType,
            $folderstructure,
            $allowDownload,
            $codeTemplate,
            $solutionCode,
            $maxAttempts,
            $iterationsCount,
            $showSolution,
            $showSolutionCode,
            $manualReview,
            $taskId
        );
        $updateStmt->execute();
        $updateStmt->close();
        $mode = 'updated';
    } else {
        $posStmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
        $posStmt->bind_param('i', $assignmentId);
        $posStmt->execute();
        $nextPos = (int)$posStmt->get_result()->fetch_assoc()['next_pos'];
        $posStmt->close();

        $insertStmt = $conn->prepare('INSERT INTO tasks (
            assignment_id, title, task_text, description, stoff,
            task_type, problem_type, folderstructure, allowDownload, position,
            code_template, solution_code,
            max_attempts, iterations_count, show_solution, show_solution_code,
            manual_review_required,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?,
            NOW(), NOW()
        )');

        $allowDownload = 1;
        $maxAttempts = 10;
        $iterationsCount = 1;
        $showSolution = 1;
        $showSolutionCode = 1;
        $manualReview = 0;

        $insertStmt->bind_param(
            'issssssiisssiiiii',
            $assignmentId,
            $title,
            $taskText,
            $description,
            $stoff,
            $taskType,
            $problemType,
            $folderstructure,
            $allowDownload,
            $nextPos,
            $codeTemplate,
            $solutionCode,
            $maxAttempts,
            $iterationsCount,
            $showSolution,
            $showSolutionCode,
            $manualReview
        );

        $insertStmt->execute();
        $taskId = (int)$conn->insert_id;
        $insertStmt->close();
        $mode = 'inserted';
    }

    $folder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Ordner konnte nicht erstellt werden: ' . $folder);
    }

    file_put_contents($folder . '/main.py', $mainPy);
    file_put_contents($folder . '/spielfeld.py', $spielfeldPy);
    file_put_contents($folder . '/function.py', $functionPy);
    file_put_contents($folder . '/README.md', $readmeMd);

    $solutionFolder = $folder . '/.solution';
    if (!is_dir($solutionFolder) && !mkdir($solutionFolder, 0755, true) && !is_dir($solutionFolder)) {
        throw new RuntimeException('Loesungsordner konnte nicht erstellt werden: ' . $solutionFolder);
    }
    file_put_contents($solutionFolder . '/main.py', $mainPy);
    file_put_contents($solutionFolder . '/spielfeld.py', $spielfeldPy);
    file_put_contents($solutionFolder . '/function.py', $functionPy);

    echo 'Goldspiel BASIC task ' . strtoupper($mode) . PHP_EOL;
    echo 'task_id=' . $taskId . PHP_EOL;
    echo 'title=' . $title . PHP_EOL;
    echo 'assignment_id=' . $assignmentId . PHP_EOL;
    echo 'folder=' . $folder . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
