<?php
/**
 * Refresh existing grid_redraw_demo init.py files to latest robust sequential version.
 *
 * Usage:
 *   php scripts/refresh_grid_redraw_demo_projects.php
 */

require_once __DIR__ . '/../config/database.php';

$newContent = <<<'PYTHON'
import time

WIDTH = 12
HEIGHT = 8

WALLS = {
    (3, 1), (3, 2), (3, 3),
    (7, 2), (8, 2),
    (5, 5), (6, 5), (7, 5),
}

player_x, player_y = 1, 1
goal_x, goal_y = 10, 6
steps = 0

def render_board(message=''):
    rows = []
    rows.append('#' * (WIDTH + 2))
    for y in range(HEIGHT):
        row = ['#']
        for x in range(WIDTH):
            if (x, y) == (player_x, player_y):
                row.append('@')
            elif (x, y) == (goal_x, goal_y):
                row.append('X')
            elif (x, y) in WALLS:
                row.append('O')
            else:
                row.append('.')
        row.append('#')
        rows.append(''.join(row))
    rows.append('#' * (WIDTH + 2))
    rows.append('')
    rows.append(f'Schritte: {steps}')
    rows.append('Steuerung: w/a/s/d, q = Ende')
    if message:
        rows.append(f'Info: {message}')
    return '\n'.join(rows)

outputClear()
redraw(render_board('Demo gestartet'))
outputFlush()

while True:
    cmd = input('Bewegung (w/a/s/d, q): ').strip().lower()

    if cmd == 'q':
        redraw(render_board('Beendet'))
        outputFlush()
        break

    moves = {
        'w': (0, -1),
        'a': (-1, 0),
        's': (0, 1),
        'd': (1, 0),
    }

    if cmd not in moves:
        redraw(render_board('Ungueltige Eingabe'))
        outputFlush()
        time.sleep(0.08)
        continue

    dx, dy = moves[cmd]
    nx = player_x + dx
    ny = player_y + dy

    if nx < 0 or nx >= WIDTH or ny < 0 or ny >= HEIGHT:
        redraw(render_board('Rand erreicht'))
        outputFlush()
        time.sleep(0.08)
        continue

    if (nx, ny) in WALLS:
        redraw(render_board('Wand getroffen'))
        outputFlush()
        time.sleep(0.08)
        continue

    player_x, player_y = nx, ny
    steps += 1
    redraw(render_board())
    outputFlush()

    if (player_x, player_y) == (goal_x, goal_y):
        redraw(render_board('Ziel erreicht!'))
        outputFlush()
        break

    time.sleep(0.08)
PYTHON;

try {
    $conn = getDbConnection();
    echo "Running refresh for grid redraw demo projects...\n";

    $stmt = $conn->prepare(
        "UPDATE project_files
         SET content = ?, file_size = ?, updated_at = NOW()
         WHERE name = 'init.py'
           AND content LIKE '%Steuerung: w/a/s/d, q = Ende%'
           AND content LIKE '%outputClear()%'
           AND content LIKE '%redraw(render_board%'
           AND content LIKE '%def main():%'"
    );

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $size = strlen($newContent);
    $stmt->bind_param('si', $newContent, $size);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    echo "Updated files: {$affected}\n";
    echo "Done.\n";

    $conn->close();
} catch (Exception $e) {
    echo "Refresh failed: " . $e->getMessage() . "\n";
    exit(1);
}
