# Projekt 45 - Schritt 03 (03_bewegung_und_kollision)

## Inhalte

### README.md

```markdown
# 03 Bewegung und Kollision

In diesem Schritt kommt die Bewegungslogik dazu.
Der Spieler bleibt ein eigenes Dictionary.
In diesem Schritt liegen `steps`, `turns` und `gold` ebenfalls im Spieler-Dict.

## Teilschritte

### 1. Reine Spielerbewegung

Zuerst wird berechnet, auf welches Feld der Spieler als Nächstes gehen würde.
Dafür werden `posX`, `posY`, `vX` und `vY` verwendet.

### 2. Drehung

Danach wird die Blickrichtung verändert.
Beim Drehen nach links oder rechts werden `vX` und `vY` entsprechend angepasst.

### 3. Kollisionsprüfung

Bevor der Spieler wirklich bewegt wird, wird geprüft, ob an der Zielposition eine Wand liegt.
Nur wenn dort keine Wand ist, wird die Position aktualisiert.

### 4. Steuerungsschleife

Am Ende wird alles in einer Schleife zusammengesetzt.
Die Schleife liest die Eingabe, rendert den aktuellen Zustand und aktualisiert den Spieler.

## spielfeld.py

```python
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
```

## function.py

```python
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
```

## main.py

```python
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
```

## Kurze Zusammenfassung

- Der Spieler wird als zentrales Dict an alle Funktionen übergeben.
- Die Bewegungslogik basiert auf `vX` und `vY`.
- Vorwärtsbewegung prüft zuerst auf Wandkollision.
- Schritte und Drehungen werden im Spielerzustand gezählt.
```

### function.py

```python
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


# Schritt 3: Neu - Bewegungs- und Kollisionsfunktionen
def naechste_position(spieler):
    return {
        'x': spieler['posX'] + spieler['vX'],
        'y': spieler['posY'] + spieler['vY'],
    }


def ist_wand(spielfeld, position):
    return spielfeld[position['y']][position['x']] == 1


# Schritt 3: Neu - Drehung links/rechts ueber vX/vY
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


# Schritt 3: Neu - Vorwaertsbewegung mit Wandpruefung
def gehe_vorwaerts(spielfeld, spieler):
    pos = naechste_position(spieler)
    if ist_wand(spielfeld, pos):
        return False

    spieler['posX'] = pos['x']
    spieler['posY'] = pos['y']
    return True


# Schritt 3: Neu - Statusausgabe fuer Schritte und Drehungen
def render_status(spieler):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']))
```

### main.py

```python
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

# Schritt 3: Neu - Interaktive Steuerungsschleife
while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler)
    outputFlush()

        # Schritt 3: Neu - Eingabe fuer Drehen/Gehen/Beenden
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
```

### spielfeld.py

```python
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
```
