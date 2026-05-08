# Projekt 46 - IDEGUI Demo (Goldspiel Event Driven)

## Dateien

### game_logic.py

```python
import random
from levels import LEVELS
from optimal_path import finde_optimalen_pfad

SYMBOLE = {
    0: '⬜',
    1: '🧱',
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


def lade_level(level_name):
    level = LEVELS[level_name]
    kopie = []
    height = len(level)
    for y in range(height):
        kopie.append(level[y][:])
    return kopie


def kopiere_spielfeld(spielfeld):
    kopie = []
    height = len(spielfeld)
    for y in range(height):
        kopie.append(spielfeld[y][:])
    return kopie


def finde_startposition(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 2:
                return {'x': x, 'y': y}

    return None


def freie_positionen(spielfeld):
    positionen = []
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 0:
                positionen.append({'x': x, 'y': y})

    return positionen


def ziehe_zufaellige_position(positionen):
    index = random.randint(0, len(positionen) - 1)
    position = positionen[index]
    positionen.pop(index)
    return position


def zaehle_goldfelder(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)
    gold = 0

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 7:
                gold = gold + 1

    return gold


def platziere_gold_bis_anzahl(spielfeld, gold_anzahl):
    bereits = zaehle_goldfelder(spielfeld)
    positionen = freie_positionen(spielfeld)

    while bereits < gold_anzahl and len(positionen) > 0:
        position = ziehe_zufaellige_position(positionen)
        spielfeld[position['y']][position['x']] = 7
        bereits = bereits + 1


def zufallsrichtung():
    richtungen = [
        {'vX': 1, 'vY': 0},
        {'vX': -1, 'vY': 0},
        {'vX': 0, 'vY': -1},
        {'vX': 0, 'vY': 1},
    ]
    index = random.randint(0, len(richtungen) - 1)
    return richtungen[index]


def platziere_spieler_mit_feldstart(spielfeld):
    start = finde_startposition(spielfeld)

    if start is not None:
        richtung = zufallsrichtung()
        spielfeld[start['y']][start['x']] = 0
        return {
            'x': start['x'],
            'y': start['y'],
            'vX': richtung['vX'],
            'vY': richtung['vY'],
        }

    positionen = freie_positionen(spielfeld)
    if len(positionen) == 0:
        return {'x': 1, 'y': 1, 'vX': 1, 'vY': 0}

    position = ziehe_zufaellige_position(positionen)
    richtung = zufallsrichtung()
    return {
        'x': position['x'],
        'y': position['y'],
        'vX': richtung['vX'],
        'vY': richtung['vY'],
    }


def richtungs_key(spieler):
    return str(spieler['vX']) + str(spieler['vY'])


def render_board(spielfeld, spieler):
    width = len(spielfeld[0])
    height = len(spielfeld)
    zeilen = []

    for y in range(height):
        ausgabe = ''
        for x in range(width):
            if x == spieler['posX'] and y == spieler['posY']:
                ausgabe = ausgabe + PFEILE[richtungs_key(spieler)]
            else:
                ausgabe = ausgabe + SYMBOLE[spielfeld[y][x]]
        zeilen.append(ausgabe)

    return '\n'.join(zeilen)


def render_pfad_overlay(spielfeld, pfad, start):
    width = len(spielfeld[0])
    height = len(spielfeld)
    pfeile_pro_feld = {}

    laenge = len(pfad)
    for i in range(laenge - 1):
        a = pfad[i]
        b = pfad[i + 1]
        dx = b['x'] - a['x']
        dy = b['y'] - a['y']

        richtung = ''
        if dx == 1 and dy == 0:
            richtung = 'R'
        elif dx == -1 and dy == 0:
            richtung = 'L'
        elif dx == 0 and dy == 1:
            richtung = 'D'
        elif dx == 0 and dy == -1:
            richtung = 'U'

        key = str(a['x']) + ',' + str(a['y'])
        if key not in pfeile_pro_feld:
            pfeile_pro_feld[key] = {}
        if richtung != '':
            pfeile_pro_feld[key][richtung] = True

    zeilen = []
    for y in range(height):
        ausgabe = ''
        for x in range(width):
            key = str(x) + ',' + str(y)
            wert = spielfeld[y][x]

            if x == start['x'] and y == start['y']:
                ausgabe = ausgabe + '👨'
                continue

            if wert == 7:
                ausgabe = ausgabe + SYMBOLE[wert]
                continue

            if key in pfeile_pro_feld:
                dirs = pfeile_pro_feld[key]
                hat_l = 'L' in dirs
                hat_r = 'R' in dirs
                hat_u = 'U' in dirs
                hat_d = 'D' in dirs

                if (hat_l and hat_r) and not (hat_u or hat_d):
                    ausgabe = ausgabe + '↔️'
                elif (hat_u and hat_d) and not (hat_l or hat_r):
                    ausgabe = ausgabe + '↕️'
                elif hat_l and hat_r and hat_u and hat_d:
                    ausgabe = ausgabe + '✳️'
                elif hat_r:
                    ausgabe = ausgabe + '➡️'
                elif hat_l:
                    ausgabe = ausgabe + '⬅️'
                elif hat_u:
                    ausgabe = ausgabe + '⬆️'
                elif hat_d:
                    ausgabe = ausgabe + '⬇️'
                else:
                    ausgabe = ausgabe + SYMBOLE[wert]
            else:
                ausgabe = ausgabe + SYMBOLE[wert]

        zeilen.append(ausgabe)

    return '\n'.join(zeilen)


def naechste_position(spieler):
    return {
        'x': spieler['posX'] + spieler['vX'],
        'y': spieler['posY'] + spieler['vY'],
    }


def ist_wand(spielfeld, position):
    return spielfeld[position['y']][position['x']] == 1


def ist_ziel(spielfeld, spieler):
    return spielfeld[spieler['posY']][spieler['posX']] == 9


def sammle_gold(spielfeld, spieler):
    if spielfeld[spieler['posY']][spieler['posX']] == 7:
        spielfeld[spieler['posY']][spieler['posX']] = 0
        spieler['gold'] = spieler['gold'] + 1
        return True
    return False


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


def richtung_label(spieler):
    key = richtungs_key(spieler)
    if key == '10':
        return 'rechts'
    if key == '-10':
        return 'links'
    if key == '0-1':
        return 'oben'
    if key == '01':
        return 'unten'
    return '-'


def starte_spiel(level_name, gold_anzahl):
    feld_basis = lade_level(level_name)
    feld_mit_gold = kopiere_spielfeld(feld_basis)
    platziere_gold_bis_anzahl(feld_mit_gold, gold_anzahl)

    start = platziere_spieler_mit_feldstart(feld_mit_gold)
    optimal = finde_optimalen_pfad(feld_mit_gold, {'x': start['x'], 'y': start['y']})
    feld = kopiere_spielfeld(feld_mit_gold)

    spieler = {
        'posX': start['x'],
        'posY': start['y'],
        'vX': start['vX'],
        'vY': start['vY'],
        'steps': 0,
        'turns': 0,
        'gold': 0,
        'goldTotal': zaehle_goldfelder(feld),
    }

    sammle_gold(feld, spieler)

    return {
        'level': level_name,
        'goldAnzahl': gold_anzahl,
        'feldMitGold': feld_mit_gold,
        'feld': feld,
        'start': start,
        'spieler': spieler,
        'optimum': optimal['optimum'],
        'optimalerPfad': optimal['pfad'],
        'eigenerPfad': [
            {'x': start['x'], 'y': start['y']},
        ],
        'gewonnen': False,
        'message': 'Nutze die Buttons zum Bewegen.',
    }


def pruefe_gewinn(state):
    if ist_ziel(state['feld'], state['spieler']) and state['spieler']['gold'] == state['spieler']['goldTotal']:
        state['gewonnen'] = True
        if state['optimum'] >= 0 and state['spieler']['steps'] > state['optimum']:
            state['message'] = 'Gewonnen, aber nicht optimal. Vergleiche die beiden Pfadfelder.'
        elif state['optimum'] >= 0 and state['spieler']['steps'] == state['optimum']:
            state['message'] = 'Perfekt! Du hast den optimalen Weg getroffen.'
        else:
            state['message'] = 'Gewonnen!'


def eigener_pfad_board_text(state):
    return render_pfad_overlay(state['feldMitGold'], state['eigenerPfad'], state['start'])


def optimal_board_text(state):
    if state['optimum'] < 0:
        return 'Kein optimaler Pfad gefunden.'
    return render_pfad_overlay(state['feldMitGold'], state['optimalerPfad'], state['start'])

__hot_reload_marker_1778160821515 = '__hot_reload_marker_1778160821515'

hot_reload_run_1778160942295 = True
```

### index.html

```html
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Goldspiel IDEGUI Demo</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
  <main class="gs-demo app-shell">
    <section class="hero">
      <p class="eyebrow">IDEGUI Event-Driven Demo</p>
      <h1>Goldspiel</h1>
      <p class="intro">Dieselbe Spielidee wie in der Konsolenversion, aber mit Buttons statt Eingabezeile.</p>
    </section>

    <section class="toolbar card">
      <div class="toolbar-group">
        <span class="toolbar-label">Level</span>
        <button type="button" data-function="load_00" name="load_00" value="00">00</button>
        <button type="button" data-function="load_01" name="load_01" value="01">01</button>
        <button type="button" data-function="load_02" name="load_02" value="02">02</button>
        <button type="button" data-function="load_03" name="load_03" value="03">03</button>
      </div>
      <div class="toolbar-group toolbar-group-input">
        <label class="toolbar-label" for="gold_count_input">Gold beim Start</label>
        <input id="gold_count_input" data-element="gold_count" type="number" min="0" step="1" value="4" />
      </div>
      <div class="toolbar-group">
        <span class="toolbar-label">Spiel</span>
        <button type="button" data-function="neu_starten" name="neu_starten" value="restart">Neu starten</button>
      </div>
    </section>

    <section class="content-grid">
      <section class="card game-panel">
        <div class="panel-head">
          <h2>Spielfeld (Live)</h2>
          <span class="status-pill" data-element="level_label">Level 03</span>
        </div>
        <pre class="board" data-element="board">Lade Spielfeld...</pre>
        <div class="controls">
          <button type="button" data-function="links" name="links" value="links">Links drehen</button>
          <button type="button" data-function="vorwaerts" name="vorwaerts" value="vorwaerts">Vorwaerts</button>
          <button type="button" data-function="rechts" name="rechts" value="rechts">Rechts drehen</button>
        </div>
        <p class="message" data-element="message">Bereit.</p>
      </section>

      <section class="card side-panel">
        <h2>Status</h2>
        <dl class="stats">
          <div><dt>Position</dt><dd data-element="status_position">-</dd></div>
          <div><dt>Richtung</dt><dd data-element="status_richtung">-</dd></div>
          <div><dt>Schritte</dt><dd data-element="status_steps">0</dd></div>
          <div><dt>Drehungen</dt><dd data-element="status_turns">0</dd></div>
          <div><dt>Gold</dt><dd data-element="status_gold">0/0</dd></div>
          <div><dt>Optimum</dt><dd data-element="status_optimum">-</dd></div>
        </dl>
        <div class="legend">
          <h3>Legende Pfadvergleich</h3>
          <p>👨 Startposition</p>
          <p>🪙 Gold bleibt sichtbar</p>
          <p>➡️ ⬅️ ⬆️ ⬇️ Richtung</p>
          <p>↔️ ↕️ Hin-und-zurueck</p>
        </div>
      </section>
    </section>

    <section class="compare-grid">
      <section class="card compare-panel">
        <div class="panel-head">
          <h2>Dein Pfad</h2>
          <span class="hint">wird live aufgezeichnet</span>
        </div>
        <pre class="board board-optimal" data-element="eigener_pfad_board">Dein Pfad wird gezeichnet...</pre>
      </section>

      <section class="card compare-panel">
        <div class="panel-head">
          <h2>Optimaler Pfad</h2>
          <span class="hint">als Referenz</span>
        </div>
        <pre class="board board-optimal" data-element="optimal_board">Optimalpfad wird berechnet...</pre>
      </section>
    </section>
  </main>
</body>
</html>
```

### init.py

```python
import idegui as ui
from game_logic import (
    starte_spiel,
    render_board,
    drehe_links,
    drehe_rechts,
    gehe_vorwaerts,
    sammle_gold,
    pruefe_gewinn,
    richtung_label,
    optimal_board_text,
    eigener_pfad_board_text,
)

DEFAULT_GOLD_ANZAHL = 4


def lese_gold_anzahl_aus_gui():
    text = ui.get('gold_count', str(DEFAULT_GOLD_ANZAHL)).strip()

    if text == '':
        ui.set('gold_count', str(DEFAULT_GOLD_ANZAHL))
        return DEFAULT_GOLD_ANZAHL

    try:
        gold_anzahl = int(text)
        if gold_anzahl < 0:
            raise ValueError()
    except Exception:
        gold_anzahl = DEFAULT_GOLD_ANZAHL

    ui.set('gold_count', str(gold_anzahl))
    return gold_anzahl


if 'APP_STATE' not in globals():
    APP_STATE = starte_spiel('03', DEFAULT_GOLD_ANZAHL)


def render_ui():
    spieler = APP_STATE['spieler']

    ui.set('level_label', 'Level ' + APP_STATE['level'])
    ui.set('gold_count', str(APP_STATE['goldAnzahl']))
    ui.set('board', render_board(APP_STATE['feld'], spieler))
    ui.set('status_position', 'x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    ui.set('status_richtung', richtung_label(spieler))
    ui.set('status_steps', str(spieler['steps']))
    ui.set('status_turns', str(spieler['turns']))
    ui.set('status_gold', str(spieler['gold']) + '/' + str(spieler['goldTotal']))
    ui.set('status_optimum', str(APP_STATE['optimum']))
    ui.set('message', APP_STATE['message'])
    ui.set('eigener_pfad_board', eigener_pfad_board_text(APP_STATE))
    ui.set('optimal_board', optimal_board_text(APP_STATE))


def lade_level(level_name):
    global APP_STATE
    gold_anzahl = lese_gold_anzahl_aus_gui()
    APP_STATE = starte_spiel(level_name, gold_anzahl)
    render_ui()


def load_00(trigger):
    lade_level('00')


def load_01(trigger):
    lade_level('01')


def load_02(trigger):
    lade_level('02')


def load_03(trigger):
    lade_level('03')


def neu_starten(trigger):
    lade_level(APP_STATE['level'])


def links(trigger):
    if APP_STATE['gewonnen']:
        APP_STATE['message'] = 'Spiel ist bereits beendet. Nutze Neu starten.'
        render_ui()
        return

    drehe_links(APP_STATE['spieler'])
    APP_STATE['spieler']['turns'] = APP_STATE['spieler']['turns'] + 1
    APP_STATE['message'] = 'Links gedreht.'
    render_ui()


def rechts(trigger):
    if APP_STATE['gewonnen']:
        APP_STATE['message'] = 'Spiel ist bereits beendet. Nutze Neu starten.'
        render_ui()
        return

    drehe_rechts(APP_STATE['spieler'])
    APP_STATE['spieler']['turns'] = APP_STATE['spieler']['turns'] + 1
    APP_STATE['message'] = 'Rechts gedreht.'
    render_ui()


def vorwaerts(trigger):
    if APP_STATE['gewonnen']:
        APP_STATE['message'] = 'Spiel ist bereits beendet. Nutze Neu starten.'
        render_ui()
        return

    moved = gehe_vorwaerts(APP_STATE['feld'], APP_STATE['spieler'])
    if moved:
        APP_STATE['spieler']['steps'] = APP_STATE['spieler']['steps'] + 1
        APP_STATE['eigenerPfad'].append({
            'x': APP_STATE['spieler']['posX'],
            'y': APP_STATE['spieler']['posY'],
        })
        gesammelt = sammle_gold(APP_STATE['feld'], APP_STATE['spieler'])
        if gesammelt:
            APP_STATE['message'] = 'Gold eingesammelt.'
        else:
            APP_STATE['message'] = 'Vorwaerts bewegt.'
        pruefe_gewinn(APP_STATE)
    else:
        APP_STATE['message'] = 'Dort ist eine Wand.'

    render_ui()


render_ui()
# __save_sync_marker_1778160878447
```

### levels.py

```python
LEVELS = {
    '00': [
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 7, 0, 0, 0, 0, 1],
        [1, 0, 0, 0, 1, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 9, 1],
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
    ],
    '01': [
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 1, 0, 1],
        [1, 0, 0, 0, 1, 0, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 1, 1, 0, 0, 0, 1],
        [1, 7, 0, 0, 0, 0, 0, 9, 1],
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
    ],
    '02': [
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 0, 1, 1, 0, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 1, 0, 0, 0, 1, 0, 1],
        [1, 0, 0, 0, 0, 0, 0, 0, 1],
        [1, 0, 0, 1, 1, 0, 0, 0, 1],
        [1, 0, 0, 7, 0, 0, 0, 9, 1],
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
    ],
    '03': [
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
        [1, 0, 0, 0, 1, 0, 0, 0, 1],
        [1, 0, 1, 0, 1, 0, 1, 0, 1],
        [1, 7, 0, 0, 0, 0, 7, 0, 1],
        [1, 0, 1, 1, 1, 0, 1, 0, 1],
        [1, 0, 0, 2, 7, 0, 0, 0, 1],
        [1, 0, 1, 0, 1, 1, 1, 0, 1],
        [1, 7, 0, 0, 0, 0, 0, 9, 1],
        [1, 1, 1, 1, 1, 1, 1, 1, 1],
    ],
}
```

### optimal_path.py

```python
from collections import deque


def finde_goldpositionen(spielfeld):
    positionen = []
    width = len(spielfeld[0])
    height = len(spielfeld)

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 7:
                positionen.append({'x': x, 'y': y})

    return positionen


def baue_gold_index(gold_positionen):
    index_map = {}
    laenge = len(gold_positionen)

    for i in range(laenge):
        pos = gold_positionen[i]
        key = str(pos['x']) + ',' + str(pos['y'])
        index_map[key] = i

    return index_map


def ist_wand(spielfeld, x, y):
    return spielfeld[y][x] == 1


def ist_ziel(spielfeld, x, y):
    return spielfeld[y][x] == 9


def key_von_zustand(x, y, gold_mask):
    return str(x) + '|' + str(y) + '|' + str(gold_mask)


def parse_zustand_key(key):
    teile = key.split('|')
    return {
        'x': int(teile[0]),
        'y': int(teile[1]),
        'goldMask': int(teile[2]),
    }


def rekonstruiere_pfad(vorgaenger, ziel_key):
    keys = []
    key = ziel_key

    while key != '':
        keys.append(key)
        key = vorgaenger[key]

    keys.reverse()

    pfad = []
    laenge = len(keys)
    for i in range(laenge):
        zustand = parse_zustand_key(keys[i])
        pfad.append({'x': zustand['x'], 'y': zustand['y']})

    return pfad


def finde_optimalen_pfad(spielfeld, start):
    width = len(spielfeld[0])
    height = len(spielfeld)

    gold_positionen = finde_goldpositionen(spielfeld)
    gold_index = baue_gold_index(gold_positionen)
    alle_gold = 0

    for i in range(len(gold_positionen)):
        alle_gold = alle_gold | (1 << i)

    start_mask = 0
    start_key_gold = str(start['x']) + ',' + str(start['y'])
    if start_key_gold in gold_index:
        index = gold_index[start_key_gold]
        start_mask = start_mask | (1 << index)

    start_key = key_von_zustand(start['x'], start['y'], start_mask)
    queue = deque()
    queue.append(start_key)

    distanz = {}
    distanz[start_key] = 0

    vorgaenger = {}
    vorgaenger[start_key] = ''

    richtungen = [
        {'dx': 1, 'dy': 0},
        {'dx': -1, 'dy': 0},
        {'dx': 0, 'dy': 1},
        {'dx': 0, 'dy': -1},
    ]

    while len(queue) > 0:
        aktueller_key = queue.popleft()
        aktuell = parse_zustand_key(aktueller_key)

        if ist_ziel(spielfeld, aktuell['x'], aktuell['y']) and aktuell['goldMask'] == alle_gold:
            pfad = rekonstruiere_pfad(vorgaenger, aktueller_key)
            return {
                'gefunden': True,
                'optimum': distanz[aktueller_key],
                'pfad': pfad,
            }

        for richtung in richtungen:
            nx = aktuell['x'] + richtung['dx']
            ny = aktuell['y'] + richtung['dy']

            if nx < 0 or ny < 0 or nx >= width or ny >= height:
                continue
            if ist_wand(spielfeld, nx, ny):
                continue

            neue_maske = aktuell['goldMask']
            gold_key = str(nx) + ',' + str(ny)
            if gold_key in gold_index:
                gold_i = gold_index[gold_key]
                neue_maske = neue_maske | (1 << gold_i)

            next_key = key_von_zustand(nx, ny, neue_maske)
            if next_key not in distanz:
                distanz[next_key] = distanz[aktueller_key] + 1
                vorgaenger[next_key] = aktueller_key
                queue.append(next_key)

    return {
        'gefunden': False,
        'optimum': -1,
        'pfad': [],
    }
```

### README.md

```markdown
# IDEGUI Demo - Goldspiel Event Driven

Dieses Projekt setzt das Goldspiel als reine IDEGUI-Demoversion um.
Die Spiellogik bleibt inhaltlich gleich, aber die Steuerung erfolgt ueber Buttons statt ueber `input()`.

## Ziel der Demo

- dieselben Regeln wie im Python-Goldspiel
- event-driven Bedienung wie beim Taschenrechner
- gute Vorlesungsdemo ohne Fokus auf Implementierungsdetails

## Prinzip

- `index.html`: sichtbare Oberflaeche
- `style.css`: Layout und Gestaltung
- `init.py`: Event-Handler und APP_STATE
- `game_logic.py`: Spiellogik, Renderer und Gewinnpruefung
- `levels.py`: feste 9x9-Spielfelder
- `optimal_path.py`: exakte Berechnung des optimalen Wegs in Schritten

## Event-Driven Idee

Wie beim Taschenrechner:
- Ein Button loest eine Python-Funktion aus.
- Diese Funktion veraendert den Zustand.
- Danach wird die Oberflaeche neu gerendert.

## Regeln

- WaeNde blockieren die Bewegung.
- Gold wird beim Betreten eingesammelt.
- Gewonnen wird erst bei Ziel **und** allen Goldmuenzen.
- Drehungen sind moeglich, zaehlen aber nicht zum Optimum.
- Das Optimum zaehlt nur Vorwaertsschritte.
- Wenn das Spielfeld ein Startfeld `2` enthaelt, wird dort fix gestartet.
- Sonst wird der Start zufaellig gesetzt.
- Vorhandenes Gold bleibt erhalten; es wird nur bis `gold_anzahl` aufgefuellt.
```

### style.css

```css
.gs-demo {
  --bg: #f4efe3;
  --paper: #fffaf0;
  --ink: #2c2418;
  --line: #8e6f47;
  --accent: #b85c38;
  --accent-dark: #8f4324;

  max-width: 1200px;
  margin: 0 auto;
  padding: 24px;
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, #fff6db 0, transparent 34%),
    linear-gradient(180deg, #f1e7d3 0%, #eadcc2 100%);
  color: var(--ink);
  font-family: Georgia, "Times New Roman", serif;
}

.gs-demo * {
  box-sizing: border-box;
}

.gs-demo .hero { margin-bottom: 20px; }
.gs-demo .eyebrow {
  margin: 0 0 8px 0;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--accent-dark);
  font-size: 12px;
}

.gs-demo h1,
.gs-demo h2,
.gs-demo h3,
.gs-demo p {
  margin-top: 0;
}

.gs-demo .intro { max-width: 720px; color: #5a4a34; }

.gs-demo .card {
  background: rgba(255, 250, 240, 0.92);
  border: 2px solid rgba(142, 111, 71, 0.5);
  border-radius: 18px;
  box-shadow: 0 14px 40px rgba(80, 52, 24, 0.12);
}

.gs-demo .toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  padding: 16px;
  margin-bottom: 20px;
}

.gs-demo .toolbar-group {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.gs-demo .toolbar-label { font-weight: 700; color: var(--accent-dark); }

.gs-demo .toolbar-group-input input {
  width: 88px;
  padding: 10px 12px;
  border-radius: 999px;
  border: 1px solid rgba(142, 111, 71, 0.55);
  background: rgba(255, 250, 240, 0.95);
  color: var(--ink);
  font: inherit;
}

.gs-demo button {
  border: none;
  border-radius: 999px;
  background: linear-gradient(180deg, var(--accent) 0%, var(--accent-dark) 100%);
  color: #fff9f4;
  font-weight: 700;
  padding: 11px 18px;
  cursor: pointer;
  transition: transform 0.12s ease;
}

.gs-demo button:hover { transform: translateY(-1px); }

.gs-demo .content-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr);
  gap: 20px;
}

.gs-demo .game-panel,
.gs-demo .side-panel,
.gs-demo .compare-panel {
  padding: 18px;
}

.gs-demo .panel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.gs-demo .status-pill,
.gs-demo .hint {
  color: var(--accent-dark);
  font-size: 14px;
}

.gs-demo .board {
  min-height: 260px;
  margin: 14px 0 18px 0;
  padding: 16px;
  border-radius: 14px;
  background: linear-gradient(180deg, #3c2e1f 0%, #d4cec7ff 100%);
  color: #fff8ea;
  border: 1px solid rgba(255, 240, 214, 0.16);
  font-family: "Courier New", monospace;
  font-size: 27px;
  line-height: 1.18;
  overflow-x: auto;
  white-space: pre;
}

.gs-demo .board-optimal { min-height: 210px; }

.gs-demo .controls {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.gs-demo .message {
  margin: 16px 0 0 0;
  min-height: 24px;
  color: var(--accent-dark);
  font-weight: 700;
}

.gs-demo .stats { margin: 0 0 22px 0; }
.gs-demo .stats div {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(142, 111, 71, 0.18);
}

.gs-demo .stats dt { font-weight: 700; }
.gs-demo .stats dd { margin: 0; text-align: right; }

.gs-demo .legend p { margin-bottom: 8px; }

.gs-demo .compare-grid {
  margin-top: 20px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
}

@media (max-width: 980px) {
  .gs-demo .content-grid,
  .gs-demo .compare-grid {
    grid-template-columns: 1fr;
  }

  .gs-demo .controls {
    grid-template-columns: 1fr;
  }

  .gs-demo .board {
    font-size: 24px;
  }
}
```
