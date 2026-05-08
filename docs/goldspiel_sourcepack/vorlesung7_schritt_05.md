# Projekt 45 - Schritt 05 (05_zufaellige_platzierung)

## Inhalte

### README.md

```markdown
## Neu in Schritt 05

Im Vergleich zu Schritt 04 sind genau diese Punkte neu:

- Das feste Spielfeld wurde zu `SPIELFELD_BASIS` ohne Startfeld (`2`) umgestellt.
- Das Modul `random` wurde eingefuehrt.
- Neue Funktion `freie_positionen(spielfeld)`: sammelt alle freien Felder (`0`).
- Neue Funktion `ziehe_zufaellige_position(positionen)`: zieht eine Position und entfernt sie aus der Liste.
- Neue Funktion `platziere_gold(spielfeld, anzahl)`: setzt Gold (`7`) zufaellig auf freie Felder.
- Neue Funktion `platziere_spieler(spielfeld)`: waehlt den Spielerstart zufaellig auf einem freien Feld und setzt eine zufaellige Startrichtung.
- Die Initialisierung in `main.py` wurde erweitert: erst Gold platzieren, dann Startposition ziehen.
- Bewegung, Kollision, Gold-Einsammeln und Zielbedingung bleiben aus Schritt 04 unveraendert.

# 05 Zufaellige Platzierung

In diesem Schritt werden Startposition und Gold nicht mehr fest kodiert,
sondern bei jedem Start neu verteilt.

## Teilschritte

### 1. Basis-Spielfeld

Das Spielfeld enthaelt nur feste Elemente:
- Waende (`1`)
- freie Felder (`0`)
- genau ein Ziel (`9`)

### 2. Gold zufaellig platzieren

Eine Hilfsfunktion sammelt alle freien Positionen (`0`).
Danach werden mehrfach zufaellige Positionen gezogen und zu Gold (`7`) gesetzt.

### 3. Spieler zufaellig platzieren

Der Spieler bekommt ebenfalls eine freie Position,
die nicht Wand und nicht Gold ist.

### 4. Spielschleife wie in Schritt 04

Bewegung, Kollision, Einsammeln und Zielpruefung bleiben gleich.
Nur die Initialisierung wurde erweitert.

## spielfeld_random.py

```python
# 0 = leer
# 1 = Wand
# 7 = Gold
# 9 = Ziel

import random

SPIELFELD_BASIS = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 0, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]


def kopiere_spielfeld(spielfeld):
    kopie = []
    height = len(spielfeld)

    for y in range(height):
        kopie.append(spielfeld[y][:])

    return kopie


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


def platziere_gold(spielfeld, anzahl):
    positionen = freie_positionen(spielfeld)
    gesetzt = 0

    while gesetzt < anzahl and len(positionen) > 0:
        position = ziehe_zufaellige_position(positionen)
        spielfeld[position['y']][position['x']] = 7
        gesetzt = gesetzt + 1


def platziere_spieler(spielfeld):
    positionen = freie_positionen(spielfeld)
    richtungen = [
        {'vX': 1, 'vY': 0},
        {'vX': -1, 'vY': 0},
        {'vX': 0, 'vY': -1},
        {'vX': 0, 'vY': 1},
    ]

    if len(positionen) == 0:
        return {'x': 1, 'y': 1, 'vX': 1, 'vY': 0}

    position = ziehe_zufaellige_position(positionen)
    richtung_index = random.randint(0, len(richtungen) - 1)
    richtung = richtungen[richtung_index]

    return {
        'x': position['x'],
        'y': position['y'],
        'vX': richtung['vX'],
        'vY': richtung['vY'],
    }


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

## functions_random.py

```python
SYMBOLE = {
    0: '⏹️',
    1: '⬛',
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
from spielfeld import SPIELFELD_BASIS, kopiere_spielfeld, platziere_gold, platziere_spieler, zaehle_goldfelder
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

feld = kopiere_spielfeld(SPIELFELD_BASIS)

gold_anzahl = 4
platziere_gold(feld, gold_anzahl)
start = platziere_spieler(feld)

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

- Das Ziel bleibt fest, Gold und Startposition sind bei jedem Start neu.
- Platzierung passiert nur auf freien Feldern (`0`).
- Das restliche Spielverhalten bleibt identisch zu Schritt 04.
```

### functions_random.py

```python
SYMBOLE = {
    0: '⏹️',
    1: '⬛',
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

### main.py

```python
from spielfeld_random import SPIELFELD_BASIS, kopiere_spielfeld, platziere_gold, platziere_spieler, zaehle_goldfelder
from functions_random import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

# Schritt 5: Neu - Basisspielfeld kopieren statt fixes Feld mit Startposition
feld = kopiere_spielfeld(SPIELFELD_BASIS)

gold_anzahl = 4
# Schritt 5: Neu - Gold zufaellig platzieren
platziere_gold(feld, gold_anzahl)
# Schritt 5: Neu - Spielerposition und Startrichtung zufaellig waehlen
start = platziere_spieler(feld)

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

### spielfeld_random.py

```python
# 0 = leer
# 1 = Wand
# 7 = Gold
# 9 = Ziel

import random

SPIELFELD_BASIS = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 0, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]


def kopiere_spielfeld(spielfeld):
    kopie = []
    height = len(spielfeld)

    for y in range(height):
        kopie.append(spielfeld[y][:])

    return kopie


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


def platziere_gold(spielfeld, anzahl):
    positionen = freie_positionen(spielfeld)
    gesetzt = 0

    while gesetzt < anzahl and len(positionen) > 0:
        position = ziehe_zufaellige_position(positionen)
        spielfeld[position['y']][position['x']] = 7
        gesetzt = gesetzt + 1


def platziere_spieler(spielfeld):
    positionen = freie_positionen(spielfeld)
    richtungen = [
        {'vX': 1, 'vY': 0},
        {'vX': -1, 'vY': 0},
        {'vX': 0, 'vY': -1},
        {'vX': 0, 'vY': 1},
    ]

    if len(positionen) == 0:
        return {'x': 1, 'y': 1, 'vX': 1, 'vY': 0}

    position = ziehe_zufaellige_position(positionen)
    richtung_index = random.randint(0, len(richtungen) - 1)
    richtung = richtungen[richtung_index]

    return {
        'x': position['x'],
        'y': position['y'],
        'vX': richtung['vX'],
        'vY': richtung['vY'],
    }


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
