<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $assignmentId = 21;
    $title = 'IDEGUI Goldspiel: 6x6 Zufallsfeld';

    $description = <<<'HTML'
<div class="task-details">
  <h4>Details</h4>
  <p>Erzeuge ein Goldspiel-Board mit folgenden Vorgaben:</p>
  <ul>
    <li><strong>6x6 Spielfeld</strong></li>
    <li>Rand komplett als Hindernis umrandet</li>
    <li><strong>3</strong> zusaetzliche Hindernisse im Innenbereich</li>
    <li><strong>5</strong> zufaellige Muenzen</li>
    <li>Eine Startposition und eine Endposition</li>
  </ul>
  <p>Mit dem Button <code>Neu erzeugen</code> soll ein neues gueltiges Feld erzeugt und angezeigt werden.</p>
</div>
HTML;

    $taskText = 'Stelle in IDEGUI ein 6x6 Goldspiel-Feld dar: Rand voll blockiert, 3 innere Hindernisse, 5 zufaellige Muenzen, Start und Ziel.';

    $stoff = <<<'HTML'
<div class="stoff-block">
  <h4>IDEGUI-Befehle (Kurzuebersicht)</h4>
  <ul>
    <li><code>ui.set("board", text)</code>: Schreibt die Board-Darstellung in ein HTML-Element mit <code>data-element="board"</code>.</li>
    <li><code>ui.set("meta", text)</code>: Schreibt Zusatzinfos (Start, Ziel, Anzahl Muenzen/Hindernisse).</li>
    <li><code>ui.get("__trigger__")</code>: Liefert den Trigger-Namen eines Buttons im code-driven Modus.</li>
    <li><code>data-run-python="true"</code> + <code>data-run-name="neu"</code>: Startet die Python-Logik beim Klick.</li>
  </ul>
</div>
HTML;

    $indexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Goldspiel 6x6 Zufallsfeld</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="card">
    <h2>Goldspiel: 6x6 Zufallsfeld</h2>
    <p class="hint">Rand ist komplett blockiert. Innen: 3 Hindernisse, 5 Muenzen, Start und Ziel.</p>

    <button class="btn" data-run-python="true" data-run-name="neu">Neu erzeugen</button>

    <pre class="board" data-element="board">Lade Spielfeld...</pre>
    <div class="meta" data-element="meta">---</div>

    <div class="legend">
      <span><code>#</code> Wand/Hindernis</span>
      <span><code>S</code> Start</span>
      <span><code>E</code> Ende</span>
      <span><code>M</code> Muenze</span>
      <span><code>.</code> frei</span>
    </div>
  </div>
</body>
</html>
HTML;

    $styleCss = <<<'CSS'
* { box-sizing: border-box; }
body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  background: #f3f4f6;
  font-family: Segoe UI, sans-serif;
  padding: 20px;
}
.card {
  width: min(720px, 95vw);
  background: #fff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
}
h2 { margin-top: 0; margin-bottom: 8px; }
.hint { margin: 0 0 12px; color: #374151; }
.btn {
  border: 0;
  border-radius: 8px;
  padding: 10px 14px;
  font-weight: 600;
  cursor: pointer;
  background: #0f766e;
  color: #fff;
}
.board {
  margin: 14px 0 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 12px;
  background: #111827;
  color: #f9fafb;
  font: 600 16px/1.5 Consolas, Menlo, monospace;
  white-space: pre;
}
.meta {
  margin-bottom: 10px;
  color: #111827;
  font-weight: 600;
}
.legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  color: #374151;
  font-size: 14px;
}
CSS;

    $ideguiPy = <<<'PY'
import idegui as ui

# Hilfsmodul fuer IDEGUI-Tasks.
PY;

    $codeTemplate = <<<'PY'
import random
import idegui as ui

WIDTH = 6
HEIGHT = 6
INNER_OBSTACLES = 3
COINS = 5


def _empty_board():
    board = [['.' for _ in range(WIDTH)] for _ in range(HEIGHT)]

    # Komplett umrandet
    for x in range(WIDTH):
        board[0][x] = '#'
        board[HEIGHT - 1][x] = '#'
    for y in range(HEIGHT):
        board[y][0] = '#'
        board[y][WIDTH - 1] = '#'

    return board


def _inner_cells():
    cells = []
    for y in range(1, HEIGHT - 1):
        for x in range(1, WIDTH - 1):
            cells.append((x, y))
    return cells


def build_random_field():
    board = _empty_board()
    inner = _inner_cells()

    # Start + Ende
    start = random.choice(inner)
    rest = [c for c in inner if c != start]
    end = random.choice(rest)
    rest = [c for c in rest if c != end]

    # 3 verteilte innere Hindernisse
    obstacles = random.sample(rest, INNER_OBSTACLES)
    rest = [c for c in rest if c not in obstacles]

    # 5 Muenzen
    coins = random.sample(rest, COINS)

    for (x, y) in obstacles:
        board[y][x] = '#'
    for (x, y) in coins:
        board[y][x] = 'M'

    sx, sy = start
    ex, ey = end
    board[sy][sx] = 'S'
    board[ey][ex] = 'E'

    return board, start, end, obstacles, coins


def board_to_text(board):
    return '\n'.join(' '.join(row) for row in board)


def render_new_field():
    board, start, end, obstacles, coins = build_random_field()
    ui.set('board', board_to_text(board))
    ui.set(
        'meta',
        f'Start={start} | Ende={end} | Muenzen={len(coins)} | Innere Hindernisse={len(obstacles)} | Rand komplett umrandet'
    )


trigger = str(ui.get('__trigger__', '') or '').strip().lower()
if trigger in ('', 'neu'):
    render_new_field()
PY;

    $solutionCode = $codeTemplate;

    $hint1 = 'Erstelle zuerst das 6x6 Grundfeld und setze den Rand komplett auf Hindernis (#).';
    $hint2 = 'Achte darauf, dass Start, Ende, Muenzen und innere Hindernisse keine Position teilen.';
    $hint3 = 'Nutze ui.set("board", ...) fuer die Darstellung und ui.set("meta", ...) fuer Zusatzinfos.';

    $findStmt = $pdo->prepare('SELECT id FROM tasks WHERE assignment_id = ? AND title = ? LIMIT 1');
    $findStmt->execute([$assignmentId, $title]);
    $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $taskId = (int)$existing['id'];
        $updateStmt = $pdo->prepare('UPDATE tasks SET
            description = ?,
            task_text = ?,
            task_type = ?,
            problem_type = ?,
            folderstructure = ?,
            allowDownload = ?,
            allow_code_ui_web_edit = ?,
            code_template = ?,
            solution_code = ?,
            hint1 = ?,
            hint2 = ?,
            hint3 = ?,
            stoff = ?,
            max_attempts = ?,
            iterations_count = ?,
            show_solution = ?,
            show_solution_code = ?,
            manual_review_required = ?,
            updated_at = NOW()
            WHERE id = ?');

        $updateStmt->execute([
            $description,
            $taskText,
            'code_ui',
            'code_completion',
            1,
            1,
            1,
            $codeTemplate,
            $solutionCode,
            $hint1,
            $hint2,
            $hint3,
            $stoff,
            10,
            1,
            1,
            1,
            0,
            $taskId,
        ]);
        $mode = 'updated';
    } else {
        $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM tasks WHERE assignment_id = ?');
        $posStmt->execute([$assignmentId]);
        $nextPos = (int)$posStmt->fetch(PDO::FETCH_ASSOC)['next_pos'];

        $insertStmt = $pdo->prepare('INSERT INTO tasks (
            assignment_id, title, description, task_text, position,
            task_type, problem_type, folderstructure, allowDownload, allow_code_ui_web_edit,
            code_template, solution_code, hint1, hint2, hint3, stoff,
            max_attempts, iterations_count, show_solution, show_solution_code,
            manual_review_required,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?,
            NOW(), NOW()
        )');

        $insertStmt->execute([
            $assignmentId,
            $title,
            $description,
            $taskText,
            $nextPos,
            'code_ui',
            'code_completion',
            1,
            1,
            1,
            $codeTemplate,
            $solutionCode,
            $hint1,
            $hint2,
            $hint3,
            $stoff,
            10,
            1,
            1,
            1,
            0,
        ]);

        $taskId = (int)$pdo->lastInsertId();
        $mode = 'inserted';
    }

    $folder = __DIR__ . '/../storage/tasks/folders/task_' . $taskId;
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Ordner konnte nicht erstellt werden: ' . $folder);
    }

    file_put_contents($folder . '/index.html', $indexHtml);
    file_put_contents($folder . '/style.css', $styleCss);
    file_put_contents($folder . '/idegui.py', $ideguiPy);
    file_put_contents($folder . '/code_ui.template.json', json_encode([
        'type' => 'code_ui',
        'template_version' => '1.1.0',
        'generated_at' => date(DATE_ATOM),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $solutionFolder = $folder . '/.solution';
    if (!is_dir($solutionFolder) && !mkdir($solutionFolder, 0755, true) && !is_dir($solutionFolder)) {
        throw new RuntimeException('Loesungsordner konnte nicht erstellt werden: ' . $solutionFolder);
    }
    file_put_contents($solutionFolder . '/index.html', $indexHtml);
    file_put_contents($solutionFolder . '/style.css', $styleCss);

    echo 'Goldspiel-Task ' . strtoupper($mode) . PHP_EOL;
    echo 'task_id=' . $taskId . PHP_EOL;
    echo 'title=' . $title . PHP_EOL;
    echo 'assignment_id=' . $assignmentId . PHP_EOL;
    echo 'folder=' . $folder . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
