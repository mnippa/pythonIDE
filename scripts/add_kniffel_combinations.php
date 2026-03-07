<?php
$conn = new mysqli('localhost', 'root', 'start123', 'pythonide');
$conn->set_charset('utf8mb4');

$projectStmt = $conn->prepare('SELECT id FROM projects WHERE name = ? ORDER BY id DESC LIMIT 1');
$projectName = 'Kniffel (Yahtzee)';
$projectStmt->bind_param('s', $projectName);
$projectStmt->execute();
$project = $projectStmt->get_result()->fetch_assoc();

if (!$project) {
    die("Project not found: {$projectName}\n");
}

$projectId = (int)$project['id'];

echo "Adding combinations display to Kniffel (ID: {$projectId})...\n";

$indexHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kniffel</title>
</head>
<body>
    <div class="game-container">
        <h1>🎲 Kniffel 🎲</h1>

        <div class="dice-section">
            <h2>Würfel</h2>
            <div class="dice-display" data-element="dice">⚀ ⚀ ⚀ ⚀ ⚀</div>
            <div class="selected" data-element="selected_dice">Keine Würfel ausgewählt</div>
            <div class="roll-info" data-element="roll_info">Würfe übrig: 3</div>
        </div>

        <div class="buttons">
            <button class="btn large" data-run-python="true" data-run-name="roll">🎲 Würfeln</button>
            <button class="btn secondary" data-run-python="true" data-run-name="new_round">🔄 Neue Runde</button>
        </div>

        <div class="hold-grid">
            <button class="btn hold" data-run-python="true" data-run-name="hold_1">Würfel 1 halten/lösen</button>
            <button class="btn hold" data-run-python="true" data-run-name="hold_2">Würfel 2 halten/lösen</button>
            <button class="btn hold" data-run-python="true" data-run-name="hold_3">Würfel 3 halten/lösen</button>
            <button class="btn hold" data-run-python="true" data-run-name="hold_4">Würfel 4 halten/lösen</button>
            <button class="btn hold" data-run-python="true" data-run-name="hold_5">Würfel 5 halten/lösen</button>
        </div>

        <div class="combinations-section">
            <h2>Mögliche Kombinationen</h2>
            <div class="combinations-display" data-element="combinations">-</div>
        </div>

        <div class="scoring-section">
            <h2>Punkte</h2>
            <div class="score" data-element="score">0</div>
        </div>

        <div class="message" data-element="message">Klicke auf „Würfeln", um die Runde zu starten</div>
    </div>
</body>
</html>
HTML;

$styleCss = <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #4a3f83 0%, #2a2a5a 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.game-container {
    background: #3a3a5a;
    border: 3px solid #ff6b9d;
    border-radius: 15px;
    padding: 24px;
    max-width: 720px;
    width: 100%;
    color: #fff;
}

h1 {
    text-align: center;
    margin-bottom: 22px;
    color: #ff6b9d;
    font-size: 32px;
}

h2 {
    color: #ff6b9d;
    margin-bottom: 10px;
    font-size: 18px;
}

.dice-section,
.combinations-section,
.scoring-section {
    margin: 16px 0;
    padding: 14px;
    border: 2px solid #ff6b9d;
    border-radius: 8px;
    background: rgba(255, 107, 157, 0.05);
}

.dice-display {
    font-size: 48px;
    text-align: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
    margin-bottom: 8px;
}

.selected {
    color: #ffcc00;
    font-size: 14px;
    text-align: center;
    min-height: 20px;
}

.roll-info {
    color: #ffdfe9;
    font-size: 13px;
    text-align: center;
    margin-top: 6px;
}

.combinations-display {
    font-size: 15px;
    padding: 12px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
    min-height: 40px;
    color: #ffdfe9;
    line-height: 1.6;
}

.buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin: 18px 0 10px;
}

.hold-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    margin: 10px 0 18px;
}

.btn {
    padding: 11px 14px;
    background: linear-gradient(135deg, #ff6b9d 0%, #ff8fb3 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.15s;
}

.btn:hover {
    transform: scale(1.03);
}

.btn.large {
    font-size: 16px;
    padding: 13px 28px;
}

.btn.secondary {
    background: linear-gradient(135deg, #6c63ff 0%, #8e85ff 100%);
}

.btn.hold {
    font-size: 13px;
}

.score {
    font-size: 44px;
    font-weight: bold;
    color: #ffcc00;
    text-align: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.3);
    border-radius: 5px;
}

.message {
    margin-top: 8px;
    padding: 12px;
    background: rgba(255, 107, 157, 0.1);
    border: 2px solid #ff6b9d;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    min-height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;

$initPy = <<<'PY'
import idegui as ui
import random
from collections import Counter

DICE_FACE = {
    1: '⚀',
    2: '⚁',
    3: '⚂',
    4: '⚃',
    5: '⚄',
    6: '⚅'
}

if 'GAME_STATE' not in globals() or not isinstance(globals().get('GAME_STATE'), dict):
    GAME_STATE = {
        'dice': [1, 1, 1, 1, 1],
        'held': [False, False, False, False, False],
        'rolls_left': 3,
        'round_started': False
    }

def calculate_score(dice):
    return sum(dice)

def check_straight(dice):
    """Prüft auf kleine und große Straßen"""
    sorted_dice = sorted(set(dice))
    
    # Große Straße (5 aufeinanderfolgende Zahlen)
    if len(sorted_dice) == 5:
        if sorted_dice == [1, 2, 3, 4, 5]:
            return 'große'
        if sorted_dice == [2, 3, 4, 5, 6]:
            return 'große'
    
    # Kleine Straße (4 aufeinanderfolgende Zahlen)
    if len(sorted_dice) >= 4:
        for i in range(len(sorted_dice) - 3):
            if sorted_dice[i:i+4] == list(range(sorted_dice[i], sorted_dice[i] + 4)):
                return 'kleine'
    
    return None

def analyze_combinations(dice):
    """Analysiert die Würfel und gibt alle gefundenen Kombinationen zurück"""
    counts = Counter(dice)
    combinations = []
    
    # Prüfe auf Pasche
    for number, count in counts.most_common():
        if count == 5:
            combinations.append(f'🎉 KNIFFEL (5x {number})')
        elif count == 4:
            combinations.append(f'Viererpasch mit {number}')
        elif count == 3:
            combinations.append(f'Dreierpasch mit {number}')
        elif count == 2:
            combinations.append(f'Zweierpasch mit {number}')
    
    # Prüfe auf Straßen
    straight = check_straight(dice)
    if straight == 'große':
        combinations.append('🎯 Große Straße')
    elif straight == 'kleine':
        combinations.append('📊 Kleine Straße')
    
    # Full House (3er + 2er)
    if len(counts) == 2:
        values = sorted(counts.values(), reverse=True)
        if values == [3, 2]:
            nums = sorted(counts.keys(), key=lambda x: counts[x], reverse=True)
            combinations.append(f'🏠 Full House ({nums[0]}x3 + {nums[1]}x2)')
    
    return combinations if combinations else ['Keine besondere Kombination']

def build_hold_text(held):
    idx = [str(i + 1) for i, val in enumerate(held) if val]
    if not idx:
        return 'Keine Würfel ausgewählt'
    return 'Gehalten: Würfel ' + ', '.join(idx)

def render_state(message=None):
    dice = GAME_STATE['dice']
    held = GAME_STATE['held']
    rolls_left = GAME_STATE['rolls_left']

    ui.set('dice', ' '.join(DICE_FACE[d] for d in dice))
    ui.set('score', str(calculate_score(dice)))
    ui.set('selected_dice', build_hold_text(held))
    ui.set('roll_info', f'Würfe übrig: {rolls_left}')
    
    # Kombinationen anzeigen
    if GAME_STATE['round_started']:
        combinations = analyze_combinations(dice)
        ui.set('combinations', ' • '.join(combinations))
    else:
        ui.set('combinations', '-')

    if message:
        ui.set('message', message)

def new_round(trigger):
    GAME_STATE['dice'] = [1, 1, 1, 1, 1]
    GAME_STATE['held'] = [False, False, False, False, False]
    GAME_STATE['rolls_left'] = 3
    GAME_STATE['round_started'] = False
    render_state('Neue Runde gestartet. Du hast 3 Würfe.')

def roll(trigger):
    if GAME_STATE['rolls_left'] <= 0:
        render_state('Keine Würfe mehr übrig. Starte eine neue Runde oder ändere Halten-Status vor dem nächsten Wurf.')
        return

    for i in range(5):
        if not GAME_STATE['held'][i]:
            GAME_STATE['dice'][i] = random.randint(1, 6)

    GAME_STATE['rolls_left'] -= 1
    GAME_STATE['round_started'] = True

    score = calculate_score(GAME_STATE['dice'])

    if GAME_STATE['rolls_left'] == 0:
        msg = f'Letzter Wurf! Punkte: {score}'
    else:
        msg = f'Gewürfelt! Punkte: {score}'

    render_state(msg)

def toggle_hold(index):
    if index < 0 or index > 4:
        return

    # Halten ist sinnvoll nach mindestens einem Wurf
    if not GAME_STATE['round_started']:
        render_state('Erst mindestens einmal würfeln, dann Würfel halten/lösen.')
        return

    GAME_STATE['held'][index] = not GAME_STATE['held'][index]
    state = 'gehalten' if GAME_STATE['held'][index] else 'freigegeben'
    render_state(f'Würfel {index + 1} wurde {state}.')

def hold_1(trigger):
    toggle_hold(0)

def hold_2(trigger):
    toggle_hold(1)

def hold_3(trigger):
    toggle_hold(2)

def hold_4(trigger):
    toggle_hold(3)

def hold_5(trigger):
    toggle_hold(4)

render_state('Klicke auf „Würfeln", um die Runde zu starten')
PY;

$updateStmt = $conn->prepare('UPDATE project_files SET content = ?, updated_at = NOW() WHERE project_id = ? AND name = ?');

$files = [
    'index.html' => $indexHtml,
    'style.css' => $styleCss,
    'init.py' => $initPy
];

foreach ($files as $name => $content) {
    $updateStmt->bind_param('sis', $content, $projectId, $name);
    if ($updateStmt->execute()) {
        echo "✓ Updated {$name}\n";
    } else {
        echo "✗ Failed {$name}: {$conn->error}\n";
    }
}

echo "Done.\n";
