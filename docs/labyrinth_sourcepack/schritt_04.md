# Schritt 04 - Demo Rechtehand vs Tremaux

Ordner: `04_Demo_Rechtehand_vs_Tremaux`
Level-Ordner: `labyrinthe`

## README

```markdown
# Schritt 04 - Live-Demo: Rechtehandregel vs Tremaux

Ziel: Live-Demonstration zweier lokaler Verfahren auf gleicher Karte, gleicher Startposition und gleicher Startrichtung.

## Demo-Eigenschaften

- Gleiche Karte fuer beide Algorithmen
- Gleiche Startposition und gleiche Startblickrichtung
- Startrichtung ist steuerbar (`e`, `w`, `n`, `s` oder `r`)
- Beide laufen parallel pro Iteration
- Ergebnisblock am Ende mit:
  - Gewinner nach Schritten
  - Schritt-Differenz
  - Drehungs-Differenz
  - kurze Einordnung

## Eingaben

- Level: `00` bis `09`
- Startrichtung: `e`, `w`, `n`, `s` oder `r` (random, einmal gezogen und fuer beide identisch)
- Delay: in `ms` (z.B. `30`, `30ms`, `0.30s`)

## Exkurs (optional): Tremaux

- Tremaux ist hier als **optionaler Exkurs** gedacht.
- Ziel des Exkurses: zeigen, wie lokale Markierungen Schleifen robuster aufloesen als reines Wandfolgen.
- Vergleichslevel:
  - `08`: Tremaux-Vorteil ohne Rechtehand-Loop (beide erreichen Ziel, Tremaux kuerzer)
  - `09`: Loop-Variante (Rechtehand kann in Zyklus geraten)
```

## Quellcode

### labyrinth_functions.py

```python
# GEMEINSAME BASISFUNKTIONEN
# Wird in SCHRITT 01 (manuell) und SCHRITT 02 (Rechtehandregel) verwendet.

SYMBOLE = {
    0: '⬜',
    1: '⬛',
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


def render(feld, spieler):
    width = len(feld[0])
    height = len(feld)

    for y in range(height):
        ausgabe = ''
        for x in range(width):
            if x == spieler['posX'] and y == spieler['posY']:
                ausgabe = ausgabe + PFEILE[richtungs_key(spieler)]
            else:
                ausgabe = ausgabe + SYMBOLE[feld[y][x]]
        print(ausgabe)


def naechste_position(spieler):
    return {
        'x': spieler['posX'] + spieler['vX'],
        'y': spieler['posY'] + spieler['vY'],
    }


def ist_wand(feld, position):
    return feld[position['y']][position['x']] == 1


def ist_ziel(feld, spieler):
    return feld[spieler['posY']][spieler['posX']] == 9


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


def gehe_vorwaerts(feld, spieler):
    pos = naechste_position(spieler)

    if ist_wand(feld, pos):
        return False

    spieler['posX'] = pos['x']
    spieler['posY'] = pos['y']
    return True


def render_status(spieler, level_name):
    print()
    print('Level: ' + level_name)
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
```

### labyrinth_rechtehand.py

```python
# SCHRITT 04 - DEMO ALGORITHMUS: RECHTEHANDREGEL (lokal, Sichtweite 1)


def _turns_between(vx_alt, vy_alt, vx_neu, vy_neu):
    if vx_alt == vx_neu and vy_alt == vy_neu:
        return 0
    if vx_alt == -vx_neu and vy_alt == -vy_neu:
        return 2
    return 1


def _frei(feld, x, y):
    height = len(feld)
    width = len(feld[0])
    if x < 0 or y < 0 or x >= width or y >= height:
        return False
    return feld[y][x] != 1


def _rechts(vx, vy):
    return -vy, vx


def _links(vx, vy):
    return vy, -vx


class RechtehandStepper:
    def __init__(self, feld, spieler):
        self.feld = feld
        self.spieler = spieler

    def schritt(self):
        vx = self.spieler['vX']
        vy = self.spieler['vY']

        prioritaet = [
            _rechts(vx, vy),
            (vx, vy),
            _links(vx, vy),
            (-vx, -vy),
        ]

        for nvx, nvy in prioritaet:
            nx = self.spieler['posX'] + nvx
            ny = self.spieler['posY'] + nvy
            if not _frei(self.feld, nx, ny):
                continue

            self.spieler['turns'] += _turns_between(vx, vy, nvx, nvy)
            self.spieler['vX'] = nvx
            self.spieler['vY'] = nvy
            self.spieler['posX'] = nx
            self.spieler['posY'] = ny
            self.spieler['steps'] += 1
            return True

        return False
```

### labyrinth_spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 2 = Start
# 9 = Ziel

import random


def lade_labyrinth(dateiname):
    zeilen = []

    with open(dateiname, 'r', encoding='utf-8') as datei:
        for roh in datei:
            text = roh.strip()
            if text != '':
                zeilen.append(text)

    if len(zeilen) == 0:
        raise Exception('Labyrinth ist leer: ' + dateiname)

    breite = len(zeilen[0])
    if breite == 0:
        raise Exception('Labyrinth hat leere Zeile: ' + dateiname)

    hoehe = len(zeilen)
    if hoehe < 4 or breite < 4:
        raise Exception('Labyrinth muss mindestens 4x4 sein: ' + dateiname)

    feld = []
    for y in range(hoehe):
        textzeile = zeilen[y]
        if len(textzeile) != breite:
            raise Exception('Zeile hat falsche Laenge in ' + dateiname + ' bei Zeile ' + str(y))

        zeile = []
        for x in range(breite):
            zeichen = textzeile[x]
            if zeichen != '0' and zeichen != '1' and zeichen != '2' and zeichen != '9':
                raise Exception('Ungueltiges Zeichen im Labyrinth: ' + zeichen)
            zeile.append(int(zeichen))
        feld.append(zeile)

    return feld


def freie_positionen(feld):
    positionen = []
    width = len(feld[0])
    height = len(feld)

    for y in range(height):
        for x in range(width):
            if feld[y][x] == 0:
                positionen.append({'x': x, 'y': y})

    return positionen


def finde_startposition(feld):
    width = len(feld[0])
    height = len(feld)

    for y in range(height):
        for x in range(width):
            if feld[y][x] == 2:
                return {'x': x, 'y': y}

    return None


def ziehe_zufaellige_position(positionen):
    index = random.randint(0, len(positionen) - 1)
    position = positionen[index]
    positionen.pop(index)
    return position


def zufallsrichtung():
    richtungen = [
        {'vX': 1, 'vY': 0},
        {'vX': -1, 'vY': 0},
        {'vX': 0, 'vY': -1},
        {'vX': 0, 'vY': 1},
    ]

    index = random.randint(0, len(richtungen) - 1)
    return richtungen[index]


def platziere_spieler_mit_startfeld(feld):
    start = finde_startposition(feld)

    if start is not None:
        richtung = zufallsrichtung()
        feld[start['y']][start['x']] = 0
        return {
            'x': start['x'],
            'y': start['y'],
            'vX': richtung['vX'],
            'vY': richtung['vY'],
        }

    positionen = freie_positionen(feld)
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
```

### labyrinth_tremaux.py

```python
# SCHRITT 04 - DEMO ALGORITHMUS: TREMAUX (lokal, Sichtweite 1 + Markierungen)


def _turns_between(vx_alt, vy_alt, vx_neu, vy_neu):
    if vx_alt == vx_neu and vy_alt == vy_neu:
        return 0
    if vx_alt == -vx_neu and vy_alt == -vy_neu:
        return 2
    return 1


def _edge_key(ax, ay, bx, by):
    if (ax, ay) <= (bx, by):
        return (ax, ay, bx, by)
    return (bx, by, ax, ay)


def _offene_nachbarn(feld, x, y):
    height = len(feld)
    width = len(feld[0])

    out = []
    for nx, ny in [(x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)]:
        if nx < 0 or ny < 0 or nx >= width or ny >= height:
            continue
        if feld[ny][nx] == 1:
            continue
        out.append((nx, ny))

    return out


class TremauxStepper:
    def __init__(self, feld, spieler):
        self.feld = feld
        self.spieler = spieler
        self.kanten_markierung = {}
        self.prev = None

    def _mark(self, ax, ay, bx, by):
        key = _edge_key(ax, ay, bx, by)
        self.kanten_markierung[key] = self.kanten_markierung.get(key, 0) + 1

    def _marks(self, ax, ay, bx, by):
        return self.kanten_markierung.get(_edge_key(ax, ay, bx, by), 0)

    def schritt(self):
        x = self.spieler['posX']
        y = self.spieler['posY']
        nachbarn = _offene_nachbarn(self.feld, x, y)
        if len(nachbarn) == 0:
            return False

        # Prioritaet Treaux:
        # 1) unmarkierte Kante, die nicht direkt zurueck fuehrt
        # 2) unmarkierte Kante
        # 3) einfach markierte Kante, die nicht direkt zurueck fuehrt
        # 4) sonst kleinste Markierung
        kandidaten = []
        for nx, ny in nachbarn:
            mark = self._marks(x, y, nx, ny)
            is_back = (self.prev is not None and nx == self.prev[0] and ny == self.prev[1])
            kandidaten.append({'x': nx, 'y': ny, 'mark': mark, 'back': is_back})

        def pick(filter_fn):
            filtered = [k for k in kandidaten if filter_fn(k)]
            if not filtered:
                return None
            filtered.sort(key=lambda k: (k['mark'], k['y'], k['x']))
            return filtered[0]

        ziel = pick(lambda k: k['mark'] == 0 and not k['back'])
        if ziel is None:
            ziel = pick(lambda k: k['mark'] == 0)
        if ziel is None:
            ziel = pick(lambda k: k['mark'] == 1 and not k['back'])
        if ziel is None:
            kandidaten.sort(key=lambda k: (k['mark'], k['back'], k['y'], k['x']))
            ziel = kandidaten[0]

        nx = ziel['x']
        ny = ziel['y']

        nvx = nx - x
        nvy = ny - y
        self.spieler['turns'] += _turns_between(self.spieler['vX'], self.spieler['vY'], nvx, nvy)

        self._mark(x, y, nx, ny)
        self.prev = (x, y)

        self.spieler['vX'] = nvx
        self.spieler['vY'] = nvy
        self.spieler['posX'] = nx
        self.spieler['posY'] = ny
        self.spieler['steps'] += 1
        return True
```

### main.py

```python
# SCHRITT 04 - LIVE DEMO: RECHTEHANDREGEL vs TREMAUX
# Beide Algorithmen laufen gleichzeitig auf derselben Karte, derselben Startposition
# und derselben Startblickrichtung.

import copy
import random
import time
from labyrinth_spielfeld import lade_labyrinth, platziere_spieler_mit_startfeld
from labyrinth_functions import render, render_status, ist_ziel
from labyrinth_rechtehand import RechtehandStepper
from labyrinth_tremaux import TremauxStepper


def parse_delay_ms(eingabe):
    text = eingabe.strip().lower().replace(' ', '')
    if text == '':
        return 30
    if text.endswith('ms'):
        text = text[:-2]
    elif text.endswith('s'):
        roh = text[:-1]
        if roh.startswith('0.') and roh.count('.') == 1:
            nachkomma = roh.split('.', 1)[1]
            if nachkomma.isdigit():
                return max(1, int(nachkomma))
        try:
            return max(1, int(float(roh) * 1000))
        except Exception:
            return 30
    try:
        return max(1, int(float(text)))
    except Exception:
        return 30


def parse_start_richtung(eingabe):
    text = eingabe.strip().lower()

    if text == '' or text == '0' or text == 'e':
        return {'vX': 1, 'vY': 0, 'label': 'EAST (fix)'}
    if text == 'w':
        return {'vX': -1, 'vY': 0, 'label': 'WEST (fix)'}
    if text == 'n':
        return {'vX': 0, 'vY': -1, 'label': 'NORTH (fix)'}
    if text == 's':
        return {'vX': 0, 'vY': 1, 'label': 'SOUTH (fix)'}

    if text == 'r' or text == 'rand' or text == 'random':
        richtungen = [
            {'vX': 1, 'vY': 0, 'label': 'EAST (random)'},
            {'vX': -1, 'vY': 0, 'label': 'WEST (random)'},
            {'vX': 0, 'vY': -1, 'label': 'NORTH (random)'},
            {'vX': 0, 'vY': 1, 'label': 'SOUTH (random)'},
        ]
        return random.choice(richtungen)

    return {'vX': 1, 'vY': 0, 'label': 'EAST (fix, fallback)'}


def urteil(delta):
    if delta == 0:
        return 'gleichauf'
    if abs(delta) <= 3:
        return 'nahe beieinander'
    return 'klarer Vorteil'


level_name = input('Level-Datei (00/01/02/03/04/05/06/07/08/09), Enter fuer 08: ').strip()
if level_name == '':
    level_name = '08'

level_datei = 'labyrinthe/' + level_name + '.txt'

try:
    feld_basis = lade_labyrinth(level_datei)
except Exception as fehler:
    print('Fehler beim Laden: ' + str(fehler))
    print('Nutze Standard-Level 08.')
    level_name = '08'
    feld_basis = lade_labyrinth('labyrinthe/08.txt')

# Einmalige Startwahl auf der Basiskarte.
# Position kommt aus dem Level (2) bzw. fallback-Logik.
start = platziere_spieler_mit_startfeld(feld_basis)

print('Start-Richtung fuer beide Algorithmen:')
print('  Enter/0/e = East (fix)')
print('  w = West, n = North, s = South')
print('  r = random (einmal gezogen, dann fuer beide identisch)')
richtung = parse_start_richtung(input('Richtung: '))

feld_rechts = copy.deepcopy(feld_basis)
feld_tremaux = copy.deepcopy(feld_basis)

spieler_rechts = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': richtung['vX'],
    'vY': richtung['vY'],
    'steps': 0,
    'turns': 0,
}

spieler_tremaux = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': richtung['vX'],
    'vY': richtung['vY'],
    'steps': 0,
    'turns': 0,
}

print('Delay in ms (z.B. 30, 30ms, 0.30s), Enter fuer 30:')
delay_ms = parse_delay_ms(input('Eingabe: '))

rechts = RechtehandStepper(feld_rechts, spieler_rechts)
tremaux = TremauxStepper(feld_tremaux, spieler_tremaux)

max_iter = 20000
iterationen = 0

while iterationen < max_iter:
    iterationen += 1

    rechts_ziel = ist_ziel(feld_rechts, spieler_rechts)
    tremaux_ziel = ist_ziel(feld_tremaux, spieler_tremaux)

    if rechts_ziel and tremaux_ziel:
        break

    if not rechts_ziel:
        rechts.schritt()

    if not tremaux_ziel:
        tremaux.schritt()

    outputClear()
    print('SCHRITT 04 DEMO - GLEICHE KARTE, GLEICHER START')
    print('Level: ' + level_name + ' | Iteration: ' + str(iterationen) + ' | Richtung: ' + richtung['label'])
    print('-------------------------------------------------')
    print()

    print('A) RECHTEHANDREGEL')
    render(feld_rechts, spieler_rechts)
    render_status(spieler_rechts, level_name)

    print()
    print('B) TREMAUX')
    render(feld_tremaux, spieler_tremaux)
    render_status(spieler_tremaux, level_name)

    print()
    print('Zwischenstand:')
    print('  Rechtehand: Schritte=' + str(spieler_rechts['steps']) + ' Drehungen=' + str(spieler_rechts['turns']) + ' Ziel=' + str(ist_ziel(feld_rechts, spieler_rechts)))
    print('  Tremaux:    Schritte=' + str(spieler_tremaux['steps']) + ' Drehungen=' + str(spieler_tremaux['turns']) + ' Ziel=' + str(ist_ziel(feld_tremaux, spieler_tremaux)))

    outputFlush()
    time.sleep(delay_ms / 1000.0)

rechts_im_ziel = ist_ziel(feld_rechts, spieler_rechts)
tremaux_im_ziel = ist_ziel(feld_tremaux, spieler_tremaux)
step_delta = spieler_rechts['steps'] - spieler_tremaux['steps']
turn_delta = spieler_rechts['turns'] - spieler_tremaux['turns']

outputClear()
print('DEMO BEENDET')
print('Startposition: x=' + str(start['x']) + ' y=' + str(start['y']))
print('Startrichtung: ' + richtung['label'])
print('Iterationen: ' + str(iterationen))
print('Rechtehand -> Schritte: ' + str(spieler_rechts['steps']) + ', Drehungen: ' + str(spieler_rechts['turns']) + ', Ziel: ' + str(rechts_im_ziel))
print('Tremaux    -> Schritte: ' + str(spieler_tremaux['steps']) + ', Drehungen: ' + str(spieler_tremaux['turns']) + ', Ziel: ' + str(tremaux_im_ziel))
print()
print('ERGEBNIS-VERGLEICH')
if rechts_im_ziel and tremaux_im_ziel:
    if step_delta > 0:
        print('Gewinner (Schritte): Tremaux')
    elif step_delta < 0:
        print('Gewinner (Schritte): Rechtehandregel')
    else:
        print('Gewinner (Schritte): Gleichstand')

    print('Schritt-Differenz (Rechtehand - Tremaux): ' + str(step_delta))
    print('Drehungs-Differenz (Rechtehand - Tremaux): ' + str(turn_delta))
    print('Einordnung Schritte: ' + urteil(step_delta))
elif rechts_im_ziel and not tremaux_im_ziel:
    print('Nur Rechtehandregel hat das Ziel erreicht.')
elif tremaux_im_ziel and not rechts_im_ziel:
    print('Nur Tremaux hat das Ziel erreicht.')
else:
    print('Keiner hat das Ziel erreicht (max_iter erreicht).')

if iterationen >= max_iter:
    print('Hinweis: max_iter erreicht.')
```

## Leveldateien

### 00.txt

```text
111111111111111
120000000000001
101111111111101
101000000000101
101011111110101
101010000010101
101010111010101
100010101000009
111010101011101
100010100010001
101110111010111
101000001010001
101011101011101
100000100000001
111111111111111
```

### 01.txt

```text
111111111111111
100000000000001
101111111111101
101000000000101
101011111110101
101010000010101
101010111010101
100010101000009
111010101011101
100010100010001
101110111010111
101000001010001
101011101011101
100000100000001
111111111111111
```

### 02.txt

```text
111111111111111111111
120010000010000010001
111011101010111110101
101010001010000000101
101010111011111111101
100010001000000000109
101110101111111110101
101000100000000010001
101011111111101011101
101010000000101010101
101110111110101010101
100010100010101010101
111010101110111010101
101010100000000010001
101010111011111110111
101010001010001010001
101010101110101011101
100010100000101010001
101111111111101010111
100000000000001000001
111111111111111111111
```

### 03.txt

```text
111111111111111111111
120010000000000000001
111011111110111111111
101000000010001000001
101111111011101011101
100000001000100010001
101110111110101110101
101000100010101000101
101111101010101011101
100010001010101000101
101010111010111110101
101010101000100000101
111010101111101111101
100010100000101000101
101010101110101011101
901010101010001000101
101010101011111010101
101010001000100010101
101111111011101110101
100000000000000010001
111111111111111111111
```

### 04.txt

```text
111111111111111111111
120000100000000000001
111110101111111111101
100010100010001000101
101110101110101010101
100000101000100010001
101111101011101111111
101000101010001000001
101010111010111011101
101010100010100010001
101010101111101110111
101010100000000010001
101010111111111011101
100010000000001000101
111111111111101110101
100000000000101000101
111111101111101111101
100000001000001000101
101111111011111010101
100000000000000010001
111111191111111111111
```

### 05.txt

```text
111111111111111111111
120000100000001000001
111110101110101011101
100000100010101010001
101111111110101010111
100000000000100010101
111111111111111110101
100000001000000010001
101110111011101111101
101010001000100000001
101011101110111111111
100010000010100000001
111010111110111011101
100010001000100010101
101111101011101110101
101000101010000010101
101110101011111010101
100000101000000010001
111110101111111110111
100000100000000000009
111111111111111111111
```

### 06.txt

```text
111111111111111
120000000000001
101111011111101
101000010000101
101011110110101
101010000010101
101010111010101
101000101010101
101110101010101
100010101000101
111010101110101
100010100010101
101110111010101
100000000010009
111111111111111
```

### 07.txt

```text
111111111111111
120000000000001
101111111111101
101000000000101
101011111110101
101010000010101
101010111010101
101000101010101
101110101010101
100010101000101
111010101110101
100010100010101
101110111010101
100000000010009
111111111111111
```

### 08.txt

```text
111111111111111
121100011000011
100000100110101
100000190000001
101100000110101
101001100011111
110100000001001
101010010100001
100101110001001
101000001101101
110000000010001
110000001011001
100110000000001
100000000101011
111111111111111
```

### 09.txt

```text
111111111111111
121010110110001
100000000000011
100010011000011
111011100100101
101010000190011
110010111000111
100000101011001
100011100011011
100101100000111
110001000110101
100001000010111
100011000100011
110000010010001
111111111111111
```
