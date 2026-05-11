# Projekt 45 - Schritt 04 (04_gold_und_ziel)

## Inhalte

### README.md

```markdown
# 04 Gold und Ziel

In diesem Schritt kommt eine neue Spielregel hinzu:
- Gold kann eingesammelt werden
- das Ziel zaehlt erst dann als erreicht, wenn alle Goldmuenzen gesammelt wurden

Der Spieler bleibt das zentrale Dictionary.
Das Spielfeld bleibt weiterhin getrennt und enthaelt die festen Goldpositionen.

## Teilschritte

### 1. Gold im Spielfeld darstellen

Gold wird mit dem Wert `7` im Spielfeld gespeichert.
Dadurch kann der Renderer Gold direkt als eigenes Symbol anzeigen.

### 2. Gold einsammeln

Wenn der Spieler auf einem Goldfeld steht, wird dieses Feld zu `0`.
Gleichzeitig wird `spieler['gold']` erhoeht.

### 3. Ziel pruefen

Das Ziel ist zwar schon im Spielfeld vorhanden, aber es reicht noch nicht aus, nur das Ziel zu betreten.
Gewonnen wird erst, wenn `gold == goldTotal` gilt.

### 4. Spielschleife erweitern

Die Schleife rendert weiter wie bisher.
Neu ist jetzt:
- Gold wird vor der Anzeige eingesammelt
- nach jedem Durchlauf wird geprueft, ob Ziel und Goldbedingung gemeinsam erfuellt sind

## spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 7, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 7, 0, 0, 0, 7, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 7, 0, 0, 0, 0, 0, 1],
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


def zaehle_goldfelder(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)
    gold = 0

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 7:
                gold = gold + 1
    return gold
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


def render_status(spieler):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + '/' + str(spieler['goldTotal']))
```

## main.py

```python
from spielfeld import SPIELFELD, finde_startposition, zaehle_goldfelder
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

feld = []
for zeile in SPIELFELD:
    feld.append(zeile[:])

start = finde_startposition(feld)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
    'gold': 0,
    'goldTotal': zaehle_goldfelder(feld),
}

while True:
    sammle_gold(feld, spieler)

    outputClear()
    render(feld, spieler)
    render_status(spieler)
    outputFlush()

    if ist_ziel(feld, spieler) and spieler['gold'] == spieler['goldTotal']:
        print('Gewonnen! Alle Goldmuenzen wurden eingesammelt.')
        break

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
        moved = gehe_vorwaerts(feld, spieler)
        if moved:
            spieler['steps'] = spieler['steps'] + 1
```

## Kurze Zusammenfassung

- Gold liegt fest im Spielfeld.
- Gold wird beim Betreten eingesammelt.
- Das Ziel reicht erst zusammen mit der Goldbedingung.
- Der Spielerzustand bleibt in einem einzigen Dictionary.
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


def naechste_position(spieler):
    return {
        'x': spieler['posX'] + spieler['vX'],
        'y': spieler['posY'] + spieler['vY'],
    }


def ist_wand(spielfeld, position):
    return spielfeld[position['y']][position['x']] == 1


# Schritt 4: Neu - Zielpruefung und Gold einsammeln
def ist_ziel(spielfeld, spieler):
    return spielfeld[spieler['posY']][spieler['posX']] == 9


# Schritt 4: Neu - Goldfeld auf 0 setzen und Zaehler erhoehen
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


def render_status(spieler):
    print()
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + '/' + str(spieler['goldTotal']))
```

### main.py

```python
from spielfeld import SPIELFELD, finde_startposition, zaehle_goldfelder
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

feld = []
for zeile in SPIELFELD:
    feld.append(zeile[:])

start = finde_startposition(feld)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
    'gold': 0,
    'goldTotal': zaehle_goldfelder(feld),
}

while True:
    # Schritt 4: Neu - Vor jeder Anzeige Gold auf aktueller Position einsammeln
    sammle_gold(feld, spieler)

    outputClear()
    render(feld, spieler)
    render_status(spieler)
    outputFlush()

    # Schritt 4: Neu - Gewinn nur mit Ziel plus allen Goldmuenzen
    if ist_ziel(feld, spieler) and spieler['gold'] == spieler['goldTotal']:
        print('Gewonnen! Alle Goldmuenzen wurden eingesammelt.')
        break

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
        moved = gehe_vorwaerts(feld, spieler)
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
    [1, 2, 0, 7, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 7, 0, 0, 0, 7, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 7, 0, 0, 0, 0, 0, 1],
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


def zaehle_goldfelder(spielfeld):
    width = len(spielfeld[0])
    height = len(spielfeld)
    gold = 0

    for y in range(height):
        for x in range(width):
            if spielfeld[y][x] == 7:
                gold = gold + 1
    return gold
```
