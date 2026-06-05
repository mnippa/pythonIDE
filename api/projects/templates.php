<?php
/**
 * Project Templates
 * Provides various starter templates for new projects
 */

class ProjectTemplates {
    
    /**
     * Get template by name
     */
    public static function getTemplate($templateName) {
        $method = 'template_' . $templateName;
        if (method_exists(self::class, $method)) {
            return self::$method();
        }
        return self::template_empty_python();
    }
    
    /**
     * Template: Leeres Python Projekt
     */
    private static function template_empty_python() {
        return [
            'project_type' => 'python',
            'files' => [
                'init.py' => [
                    'content' => "# Dein Python-Projekt\n\n# Hier kannst du mit Python loslegen!\nprint('Hallo Welt!')\n",
                    'mime_type' => 'text/x-python'
                ]
            ]
        ];
    }

        /**
         * Template: Kleines Datenbank-Projekt (Modell + Testdaten + SQL-Export)
         */
        private static function template_db_small() {
                return [
                        'project_type' => 'db_small',
                        'files' => [
                                'init.py' => [
                                        'content' => "# DB Small Projekt\n# Die Modellierung erfolgt im rechten Panel (Tabellen/Testdaten/SQL-Export).\n",
                                        'mime_type' => 'text/x-python'
                                ],
                                'db_model.json' => [
                                        'content' => <<<'JSON'
{
    "version": 2,
    "activeDatabaseIndex": 0,
    "databases": [
        {
            "name": "Zwischenstand 1",
            "tables": [
                {
                    "name": "student",
                    "columns": [
                        { "name": "id", "type": "AUTO", "pk": true, "fk": false, "default": "" },
                        { "name": "name", "type": "TEXT", "pk": false, "fk": false, "default": "" },
                        { "name": "semester", "type": "AUTO", "pk": false, "fk": false, "default": "" }
                    ],
                    "rows": [
                        { "name": "Ada", "semester": "2" },
                        { "name": "Turing", "semester": "4" }
                    ]
                }
            ]
        }
    ]
}
JSON
                                        ,
                                        'mime_type' => 'application/json'
                                ],
                                'db_export.sql' => [
                                        'content' => "-- SQL Export wird im DB-Designer erzeugt.\n",
                                        'mime_type' => 'application/sql'
                                ]
                        ]
                ];
        }
    
    /**
     * Template: Leeres Python-HTML Projekt
     */
    private static function template_empty_python_html() {
        return [
            'project_type' => 'mixed',
            'files' => [
                'init.py' => [
                    'content' => <<<'PYTHON'
# Python-HTML Projekt mit idegui
import idegui as ui

# Hier kannst du deine Python-Logik schreiben
# Verwende ui.get() und ui.set() um mit HTML zu interagieren

PYTHON
                    ,
                    'mime_type' => 'text/x-python'
                ],
                'index.html' => [
                    'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mein Projekt</title>
</head>
<body>
    <div class="container">
        <h1>Willkommen</h1>
        <p>Bearbeite diese HTML-Datei und die init.py, um dein Projekt zu erstellen.</p>
    </div>
</body>
</html>
HTML
                    ,
                    'mime_type' => 'text/html'
                ],
                'style.css' => [
                    'content' => <<<'CSS'
body {
    font-family: system-ui, -apple-system, sans-serif;
    margin: 0;
    padding: 20px;
    background: #f5f5f5;
}

.container {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h1 {
    color: #333;
    margin-bottom: 20px;
}
CSS
                    ,
                    'mime_type' => 'text/css'
                ]
            ]
        ];
    }
    
    /**
     * Template: Python-HTML mit direkter Python-Logik
     */
    private static function template_python_logic() {
        return [
            'project_type' => 'mixed',
            'files' => [
                'init.py' => [
                    'content' => <<<'PYTHON'
# Python-HTML Projekt mit direkter Logik
import idegui as ui

# Direkte Python-Logik: Code wird beim Start ausgeführt
# und setzt direkt Werte in HTML-Elementen

# Beispiel: Berechnung durchführen
zahl1 = 15
zahl2 = 7
ergebnis = zahl1 + zahl2

# Werte in HTML setzen (data-element Attribute)
ui.set('zahl1', str(zahl1))
ui.set('zahl2', str(zahl2))
ui.set('ergebnis', str(ergebnis))
ui.set('nachricht', f'{zahl1} + {zahl2} = {ergebnis}')

# Listen-Ausgabe
fruechte = ['Apfel', 'Banane', 'Orange', 'Erdbeere']
ui.set('liste', ', '.join(fruechte))

PYTHON
                    ,
                    'mime_type' => 'text/x-python'
                ],
                'index.html' => [
                    'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Python Logik Beispiel</title>
</head>
<body>
    <div class="container">
        <h1>Python-Logik Beispiel</h1>
        <p>Python führt Berechnungen durch und zeigt Ergebnisse here an.</p>
        
        <div class="output-box">
            <h2>Berechnung</h2>
            <p><strong>Zahl 1:</strong> <span data-element="zahl1">-</span></p>
            <p><strong>Zahl 2:</strong> <span data-element="zahl2">-</span></p>
            <p><strong>Ergebnis:</strong> <span data-element="ergebnis">-</span></p>
            <p class="message" data-element="nachricht">Warte auf Berechnung...</p>
        </div>
        
        <div class="output-box">
            <h2>Liste</h2>
            <p data-element="liste">Keine Daten</p>
        </div>
    </div>
</body>
</html>
HTML
                    ,
                    'mime_type' => 'text/html'
                ],
                'style.css' => [
                    'content' => <<<'CSS'
body {
    font-family: system-ui, -apple-system, sans-serif;
    margin: 0;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.container {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

h1 {
    color: #667eea;
    margin-bottom: 10px;
}

h2 {
    color: #764ba2;
    font-size: 18px;
    margin-bottom: 15px;
}

.output-box {
    margin: 25px 0;
    padding: 20px;
    background: #f8f9ff;
    border-left: 4px solid #667eea;
    border-radius: 6px;
}

.output-box p {
    margin: 8px 0;
}

.message {
    color: #667eea;
    font-weight: 600;
    font-size: 16px;
    margin-top: 15px;
}

[data-element] {
    color: #764ba2;
    font-weight: 600;
}
CSS
                    ,
                    'mime_type' => 'text/css'
                ]
            ]
        ];
    }
    
    /**
     * Template: Python-HTML mit Event-Logik
     */
    private static function template_event_logic() {
        return [
            'project_type' => 'mixed',
            'files' => [
                'init.py' => [
                    'content' => <<<'PYTHON'
# Python-HTML Projekt mit Event-Handler-Logik
import idegui as ui

# Event-basierte Logik: Funktionen werden bei Button-Klicks aufgerufen
# HTML-Elemente mit data-run="true" und name="funktionsname"
# rufen die entsprechende Python-Funktion auf

def berechnen(trigger):
    """Wird aufgerufen wenn der Berechnen-Button geklickt wird"""
    # Werte aus HTML-Inputs lesen
    zahl1_str = ui.get('input1')
    zahl2_str = ui.get('input2')
    
    # Validierung und Berechnung
    try:
        zahl1 = float(zahl1_str) if zahl1_str else 0
        zahl2 = float(zahl2_str) if zahl2_str else 0
        ergebnis = zahl1 + zahl2
        
        # Ergebnis zurück ins HTML schreiben
        ui.set('ergebnis', str(ergebnis))
        ui.set('nachricht', f'✓ Berechnung erfolgreich: {zahl1} + {zahl2} = {ergebnis}')
    except ValueError:
        ui.set('nachricht', '❌ Fehler: Bitte gib gültige Zahlen ein!')

def reset(trigger):
    """Wird aufgerufen wenn der Reset-Button geklickt wird"""
    ui.set('input1', '')
    ui.set('input2', '')
    ui.set('ergebnis', '0')
    ui.set('nachricht', 'Felder zurückgesetzt.')

# Initialisierung beim Start
ui.set('ergebnis', '0')
ui.set('nachricht', 'Gib zwei Zahlen ein und klicke auf Berechnen.')

PYTHON
                    ,
                    'mime_type' => 'text/x-python'
                ],
                'index.html' => [
                    'content' => <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event-Logik Beispiel</title>
</head>
<body>
    <div class="container">
        <h1>Event-Handler Beispiel</h1>
        <p>Buttons lösen Python-Funktionen aus (Event-Handler Pattern).</p>
        
        <div class="input-group">
            <label>Zahl 1:</label>
            <input type="number" data-element="input1" placeholder="0" value="5">
        </div>
        
        <div class="input-group">
            <label>Zahl 2:</label>
            <input type="number" data-element="input2" placeholder="0" value="3">
        </div>
        
        <div class="buttons">
            <button class="btn primary" data-run="true" name="berechnen">
                🔢 Berechnen
            </button>
            <button class="btn secondary" data-run="true" name="reset">
                🔄 Reset
            </button>
        </div>
        
        <div class="result-box">
            <h2>Ergebnis</h2>
            <div class="result" data-element="ergebnis">0</div>
        </div>
        
        <div class="message" data-element="nachricht">
            Gib zwei Zahlen ein und klicke auf Berechnen.
        </div>
    </div>
</body>
</html>
HTML
                    ,
                    'mime_type' => 'text/html'
                ],
                'style.css' => [
                    'content' => <<<'CSS'
body {
    font-family: system-ui, -apple-system, sans-serif;
    margin: 0;
    padding: 20px;
    background: linear-gradient(135deg, #0093E9 0%, #80D0C7 100%);
    min-height: 100vh;
}

.container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

h1 {
    color: #0093E9;
    margin-bottom: 10px;
}

h2 {
    color: #0093E9;
    font-size: 18px;
    margin-bottom: 10px;
}

.input-group {
    margin: 15px 0;
}

.input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.input-group input {
    width: 100%;
    padding: 10px;
    border: 2px solid #80D0C7;
    border-radius: 6px;
    font-size: 16px;
}

.buttons {
    display: flex;
    gap: 10px;
    margin: 25px 0;
}

.btn {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    font-size: 15px;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn:hover {
    transform: scale(1.03);
}

.btn.primary {
    background: linear-gradient(135deg, #0093E9 0%, #80D0C7 100%);
    color: white;
}

.btn.secondary {
    background: #e0e0e0;
    color: #333;
}

.result-box {
    margin: 25px 0;
    padding: 20px;
    background: #f0f9ff;
    border-radius: 8px;
    border: 2px solid #80D0C7;
}

.result {
    font-size: 36px;
    font-weight: bold;
    color: #0093E9;
    text-align: center;
    padding: 10px;
}

.message {
    margin-top: 20px;
    padding: 15px;
    background: #fff3cd;
    border: 2px solid #ffc107;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
}
CSS
                    ,
                    'mime_type' => 'text/css'
                ]
            ]
        ];
    }
    
    /**
     * Template: Kniffel Demo
     */
    private static function template_kniffel_demo() {
        return self::getKniffelTemplate();
    }
    
    /**
     * Template: Blackjack Demo
     */
    private static function template_blackjack_demo() {
        return self::getBlackjackTemplate();
    }

    /**
     * Template: Grid Redraw Demo (normal run mode)
     */
    private static function template_grid_redraw_demo() {
        $python = <<<'PYTHON'
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

        return [
            'project_type' => 'python',
            'files' => [
                'init.py' => [
                    'content' => $python,
                    'mime_type' => 'text/x-python'
                ]
            ]
        ];
    }
    
    /**
     * Get Kniffel game template (complete)
     */
    private static function getKniffelTemplate() {
        $html = <<<'HTML'
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
            <button class="btn large" data-run="true" name="action" value="roll">🎲 Würfeln</button>
            <button class="btn secondary" data-run="true" name="action" value="new_round">🔄 Neue Runde</button>
        </div>

        <div class="hold-grid">
            <button class="btn hold" data-run="true" name="hold" value="1">Würfel 1 halten/lösen</button>
            <button class="btn hold" data-run="true" name="hold" value="2">Würfel 2 halten/lösen</button>
            <button class="btn hold" data-run="true" name="hold" value="3">Würfel 3 halten/lösen</button>
            <button class="btn hold" data-run="true" name="hold" value="4">Würfel 4 halten/lösen</button>
            <button class="btn hold" data-run="true" name="hold" value="5">Würfel 5 halten/lösen</button>
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

        $css = <<<'CSS'
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

        $python = <<<'PYTHON'
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

def action(trigger):
    action_value = str(getattr(trigger, 'value', '') or '')
    if action_value == 'roll':
        roll(trigger)
    elif action_value == 'new_round':
        new_round(trigger)

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

def hold(trigger):
    try:
        index = int(trigger.value) - 1
    except (TypeError, ValueError):
        return
    toggle_hold(index)

render_state('Klicke auf „Würfeln", um die Runde zu starten')
PYTHON;

        return [
            'project_type' => 'mixed',
            'files' => [
                'index.html' => ['content' => $html, 'mime_type' => 'text/html'],
                'style.css' => ['content' => $css, 'mime_type' => 'text/css'],
                'init.py' => ['content' => $python, 'mime_type' => 'text/x-python']
            ]
        ];
    }
    
    /**
     * Get Blackjack game template (complete)
     */
    private static function getBlackjackTemplate() {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Blackjack</title>
</head>
<body>
    <div class="game-container">
        <h1>♠️ Blackjack ♠️</h1>
        
        <div class="section">
            <h2>Dealer</h2>
            <div class="hand" data-element="dealer_cards">---</div>
            <p>Summe: <span data-element="dealer_sum">0</span></p>
        </div>
        
        <div class="section">
            <h2>Spieler</h2>
            <div class="hand" data-element="player_cards">---</div>
            <p>Summe: <span data-element="player_sum">0</span></p>
        </div>
        
        <div class="buttons">
            <button class="btn" data-run="true" name="new_game">🎰 Neues Spiel</button>
            <button class="btn" data-run="true" name="hit">✋ Hit</button>
            <button class="btn" data-run="true" name="stand">✋ Stand</button>
        </div>
        
        <div class="result" data-element="message">Klicke "Neues Spiel" zum Starten</div>
    </div>
</body>
</html>
HTML;

        $css = <<<'CSS'
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Arial', sans-serif;
    background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.game-container {
    background: #0d3d1f;
    border: 3px solid #ffd700;
    border-radius: 15px;
    padding: 30px;
    max-width: 600px;
    color: #fff;
}

h1 {
    text-align: center;
    margin-bottom: 30px;
    color: #ffd700;
    font-size: 32px;
}

.section {
    margin: 20px 0;
    padding: 15px;
    border: 2px solid #ffd700;
    border-radius: 8px;
    background: rgba(255, 215, 0, 0.05);
}

.section h2 {
    color: #ffd700;
    margin-bottom: 10px;
    font-size: 18px;
}

.hand {
    background: #1a472a;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 10px;
    font-size: 18px;
    font-weight: bold;
    font-family: monospace;
}

.buttons {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin: 30px 0;
}

.btn {
    padding: 12px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: #000;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: transform 0.2s;
}

.btn:hover {
    transform: scale(1.05);
}

.result {
    padding: 15px;
    background: rgba(255, 215, 0, 0.1);
    border: 2px solid #ffd700;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;

        $python = <<<'PYTHON'
import idegui as ui
import random

def new_game(trigger):
    """Neues Blackjack-Spiel starten"""
    deck = create_deck()
    player_hand = [deal_card(deck), deal_card(deck)]
    dealer_hand = [deal_card(deck), deal_card(deck)]
    
    # Speichern im HTML (hidden)
    ui.set('player_cards', format_hand(player_hand))
    ui.set('player_sum', str(calculate_sum(player_hand)))
    ui.set('dealer_cards', format_hand([dealer_hand[0], '?']))
    ui.set('message', f'Spieler: {calculate_sum(player_hand)} | Dealer zeigt: {dealer_hand[0]}')
    
    # Game state speichern in data-element (global)
    global GAME_STATE
    GAME_STATE = {
        'deck': deck,
        'player': player_hand,
        'dealer': dealer_hand,
        'game_over': False
    }

def hit(trigger):
    """Spieler zieht eine Karte"""
    if 'GAME_STATE' not in globals():
        ui.set('message', 'Bitte erst "Neues Spiel" starten!')
        return
    
    global GAME_STATE
    if GAME_STATE['game_over']:
        ui.set('message', 'Spiel vorbei! Neues Spiel starten.')
        return
    
    GAME_STATE['player'].append(deal_card(GAME_STATE['deck']))
    player_sum = calculate_sum(GAME_STATE['player'])
    
    ui.set('player_cards', format_hand(GAME_STATE['player']))
    ui.set('player_sum', str(player_sum))
    
    if player_sum > 21:
        ui.set('message', '❌ BUST! Spieler über 21 - Dealer gewinnt!')
        GAME_STATE['game_over'] = True
    else:
        ui.set('message', f'Karte gezogen! Summe: {player_sum}')

def stand(trigger):
    """Spieler möchte nicht mehr ziehen"""
    if 'GAME_STATE' not in globals():
        ui.set('message', 'Bitte erst "Neues Spiel" starten!')
        return
    
    global GAME_STATE
    GAME_STATE['game_over'] = True
    
    # Dealer zieht bis 17
    while calculate_sum(GAME_STATE['dealer']) < 17:
        GAME_STATE['dealer'].append(deal_card(GAME_STATE['deck']))
    
    player_sum = calculate_sum(GAME_STATE['player'])
    dealer_sum = calculate_sum(GAME_STATE['dealer'])
    
    ui.set('dealer_cards', format_hand(GAME_STATE['dealer']))
    ui.set('dealer_sum', str(dealer_sum))
    
    if dealer_sum > 21:
        ui.set('message', f'✅ Dealer BUST! Spieler gewinnt! ({player_sum} vs {dealer_sum})')
    elif player_sum > dealer_sum:
        ui.set('message', f'✅ Spieler gewinnt! ({player_sum} vs {dealer_sum})')
    elif player_sum < dealer_sum:
        ui.set('message', f'❌ Dealer gewinnt! ({player_sum} vs {dealer_sum})')
    else:
        ui.set('message', f'🤝 Push! Unentschieden ({player_sum})')

def create_deck():
    cards = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A']
    return cards * 4  # 4 Decks

def deal_card(deck):
    return deck.pop(random.randint(0, len(deck)-1))

def calculate_sum(hand):
    total = 0
    aces = 0
    for card in hand:
        if card == 'A':
            aces += 1
            total += 11
        elif card in ['J', 'Q', 'K']:
            total += 10
        else:
            total += int(card)
    
    while total > 21 and aces > 0:
        total -= 10
        aces -= 1
    
    return total

def format_hand(hand):
    return ' '.join(str(c) for c in hand)

# Initialize
GAME_STATE = None
ui.set('message', 'Willkommen zu Blackjack! Klicke "Neues Spiel"')
PYTHON;

        return [
            'project_type' => 'mixed',
            'files' => [
                'index.html' => ['content' => $html, 'mime_type' => 'text/html'],
                'style.css' => ['content' => $css, 'mime_type' => 'text/css'],
                'init.py' => ['content' => $python, 'mime_type' => 'text/x-python']
            ]
        ];
    }
}
