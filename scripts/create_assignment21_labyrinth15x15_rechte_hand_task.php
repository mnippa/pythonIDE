<?php
require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
    $assignmentId = 21;
    $title = 'Labyrinth Grundspiel: 15x15 Rechte-Hand-Regel';

    $taskText = 'Labyrinth im Schritt-3-Muster: 15x15 mit Gaengen. Das Spielfeld steht fest als 2D-Array im Code. Die rechte-Hand-Regel braucht etwa 20-25 Schritte, laeuft einmal knapp am Ziel vorbei und erzwingt geradeaus, links sowie Umkehren.';
    $description = 'Konsolen-Labyrinth ohne IDEGUI im Stil von Schritt 3 (Symbole, Richtungsvektor, l/r/Enter). Das Spielfeld steht sichtbar als festes Array in spielfeld.py und kann dort leicht angepasst werden.';
    $stoff = 'Labyrinth, Rechte-Hand-Regel, Richtungsvektoren, drehen/gehen, if-Logik.';

    $taskType = 'code';
    $problemType = 'code_completion';
    $folderstructure = 1;

    $mainPy = <<<'PY'
from spielfeld import SPIELFELD, START_POS, GOAL_POS, ANALYSE
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, ist_ziel

spieler = {
    'posX': START_POS['x'],
    'posY': START_POS['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
}

while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler, GOAL_POS, ANALYSE)
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
        render_status(spieler, GOAL_POS, ANALYSE)
        print('Ziel erreicht.')
        break
PY;

    $spielfeldPy = <<<'PY'

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
    [1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 1, 1, 0, 1, 1, 1, 1, 1, 0, 1, 0, 1],
    [1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1, 0, 1, 0, 1],
    [1, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1],
    [1, 0, 1, 9, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
    [1, 0, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 1, 0, 0, 0, 1],
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
PY;

    $functionPy = <<<'PY'
SYMBOLE = {
    0: '⬜',
    1: '⬛',
    2: '🟦',
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
    return True


def render_status(spieler, goal_pos, analyse):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Ziel: x=' + str(goal_pos['x']) + ' y=' + str(goal_pos['y']))
    print('Hinweis Rechte-Hand-Regel: rechts vor geradeaus vor links, sonst umkehren')
    print('Verifikation (Autoanalyse): Schritte=' + str(analyse['steps']))
    print(
        'Geradeaus=' + str(analyse['actions']['straight']) +
        ' | Links=' + str(analyse['actions']['left']) +
        ' | Umkehren=' + str(analyse['actions']['u_turn'])
    )
    print('Knapp am Ziel vorbei: ' + str(analyse['passed_near_goal']))
PY;

    $readmeMd = <<<'MD'
# Labyrinth 15x15 - Rechte-Hand-Regel

Im selben Grundmuster wie Schritt 3:
- Steuerung mit `l`, `r`, `Enter`, `q`
- Richtungsvektor und Pfeilsymbol als Spieleranzeige
- Konsolen-Render in jeder Runde

Das Spielfeld steht fest als 2D-Array in `spielfeld.py` und hat folgende Eigenschaften:
- etwa 20-25 Schritte bis zum Ziel
- einmal knapp am Ziel vorbei
- geradeaus, links und umkehren kommen mindestens einmal vor

Regel fuer die Navigation:
1. Wenn rechts frei ist: rechts drehen und gehen
2. Sonst wenn vorne frei: geradeaus gehen
3. Sonst wenn links frei: links drehen und gehen
4. Sonst: umkehren
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

    echo 'Labyrinth RIGHT-HAND task ' . strtoupper($mode) . PHP_EOL;
    echo 'task_id=' . $taskId . PHP_EOL;
    echo 'title=' . $title . PHP_EOL;
    echo 'assignment_id=' . $assignmentId . PHP_EOL;
    echo 'folder=' . $folder . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
