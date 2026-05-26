<?php
require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();
$assignmentId = 32;
$title = '03_goldspiel_doppelschritt';

// Remove old task with same title to avoid duplicates.
$del = $conn->prepare('DELETE FROM tasks WHERE assignment_id = ? AND title = ?');
$del->bind_param('is', $assignmentId, $title);
$del->execute();
$deleted = $del->affected_rows;
$del->close();

// Next position in assignment.
$posStmt = $conn->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
$posStmt->bind_param('i', $assignmentId);
$posStmt->execute();
$nextPos = (int)$posStmt->get_result()->fetch_assoc()['next_pos'];
$posStmt->close();

$taskText = 'Implementierung Doppelschritt';
$description = 'Manueller Goldspiel-Ordnertask auf Basis 03_bewegung_und_kollision.';
$stoff = 'Dateien aus Goldspiel Schritt 03 sind als Vorlage vorhanden.';
$taskType = 'code';
$folderstructure = 1;


$ins = $conn->prepare(
    'INSERT INTO tasks (
        assignment_id, title, task_text, description, stoff,
        task_type, folderstructure, position, code_template, solution_code,
        created_at, updated_at
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
);
$ins->bind_param(
    'isssssiiss',
    $assignmentId,
    $title,
    $taskText,
    $description,
    $stoff,
    $taskType,
    $folderstructure,
    $nextPos,
    $codeTemplate,
    $solutionCode
);

if (!$ins->execute()) {
    echo 'ERROR insert task: ' . $ins->error . PHP_EOL;
    exit(1);
}
$taskId = (int)$conn->insert_id;
$ins->close();

$folder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
    echo 'ERROR create folder: ' . $folder . PHP_EOL;
    exit(1);
}

$spielfeldPy = <<<'PY'
# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]


def finde_startposition(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 2:
                return {'x': x, 'y': y}
    return {'x': 1, 'y': 1}
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


def render_status(spieler):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']))
PY;

$mainPy = <<<'PY'
from spielfeld import SPIELFELD, finde_startposition
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts

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

while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler)
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
PY;

$codeTemplate = $mainPy;
$solutionCode = str_replace("'vX': 1,", "'vX': 2,", $mainPy);

$readmeMd = <<<'MD'
# 03 Bewegung und Kollision

Vorlage aus Goldspiel Schritt 03.

Ziel dieser Variante: Implementierung Doppelschritt.
MD;

$reflexionMd = <<<'MD'
Was fuer systematische Problem erkennst du nach erfolgreicher Implementierung?
Probiere es aus und nenne Problempunkte.
MD;

file_put_contents($folder . '/spielfeld.py', $spielfeldPy);
file_put_contents($folder . '/function.py', $functionPy);
file_put_contents($folder . '/main.py', $mainPy);
file_put_contents($folder . '/README.md', $readmeMd);
file_put_contents($folder . '/reflexion.md', $reflexionMd);

echo 'Assignment: ' . $assignmentId . PHP_EOL;
echo 'Deleted existing with same title: ' . $deleted . PHP_EOL;
echo 'Created task id: ' . $taskId . PHP_EOL;
echo 'Folder: ' . $folder . PHP_EOL;
