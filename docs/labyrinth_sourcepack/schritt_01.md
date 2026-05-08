# Schritt 01 - Ausgangssituation (manuell)

Ordner: `01_Labyrint_manuell`
Level-Ordner: `labyrinthe`

## README

```markdown
# Schritt 01 - Ausgangssituation (manuell)

Dies ist die reine manuelle Ausgangsversion.

## Fokus

- Steuerung: `l`, `r`, `Enter`, `q`
- Keine automatische Loesung
- Fokus auf Bewegung, Kollision, Orientierung

## Level

- Verfuegbar: `00` bis `09`
- Standard in Schritt 01 bleibt manuell, ohne Algorithmusvergleich.
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

### main.py

```python
# SCHRITT 01 - MANUELL
# Ausgangssituation: Nur manuelle Steuerung ohne Algorithmus.

import os
from labyrinth_spielfeld import lade_labyrinth, platziere_spieler_mit_startfeld
from labyrinth_functions import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, ist_ziel

level_name = input('Level-Datei (00/01/02/03/04/05/06/07/08/09), Enter fuer 07: ').strip()
if level_name == '':
    level_name = '07'

level_datei = 'labyrinthe/' + level_name + '.txt'

try:
    feld = lade_labyrinth(level_datei)
except Exception as fehler:
    print('Fehler beim Laden: ' + str(fehler))
    print('Nutze Standard-Level 07.')
    level_name = '07'
    level_datei = 'labyrinthe/07.txt'
    feld = lade_labyrinth(level_datei)

start = platziere_spieler_mit_startfeld(feld)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': start['vX'],
    'vY': start['vY'],
    'steps': 0,
    'turns': 0,
}

# SCHRITT 01 - HAUPTSCHLEIFE (MANUELL)
while True:
    outputClear()
    render(feld, spieler)
    render_status(spieler, level_name)
    outputFlush()

    if ist_ziel(feld, spieler):
        print('Gewonnen! Ausgang erreicht.')
        break

    cmd = input('l / r / Enter / q: ').strip().lower()

    if cmd == 'q':
        print('Beendet.')
        break

    # SCHRITT 01 - MANUELLE EINGABEVERARBEITUNG
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
