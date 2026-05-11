# Projekt 45 - Schritt 02 (02_spieler_repraesentation_und_visualisierung)

## Inhalte

### README.md

```markdown
# 02 Spieler-Repräsentation und Visualisierung

In diesem Schritt wird der Spieler als eigenes Dictionary eingefuehrt:
- `posX`, `posY` fuer die Position
- `vX`, `vY` fuer die Blickrichtung (Richtungsvektor)

Der Spieler wird nicht im Spielfeld gespeichert.
Der Renderer zeichnet den Spieler als Overlay waehrend des Rasterdurchlaufs.

## Wie holen wir die Startposition?

Die Startposition wird aus dem Spielfeld gelesen:
- zuerst werden `width` und `height` bestimmt
- danach wird nach der Zelle mit Wert `2` gesucht
- Rueckgabe als Dictionary `{ 'x': ..., 'y': ... }`

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
```

## main.py

```python
from spielfeld import SPIELFELD, finde_startposition
from function import render

start = finde_startposition(SPIELFELD)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 0,
    'vY': 0,
}

# Didaktik: Richtung wird im main.py vor dem Rendern gesetzt.
spieler['vX'] = 1
spieler['vY'] = 0

outputClear()
render(SPIELFELD, spieler)
outputFlush()
```

## Kurze Zusammenfassung

- Spieler und Spielfeld sind getrennt.
- Die Spielfeldgroesse wird als `width` und `height` ausgelesen.
- Der Renderer verwendet `for`-Schleifen fuer die Ausgabe.
- Das Leerfeld nutzt `⏹️`, damit das Raster rechteckig wirkt.
- Der Idle-State `00` ist als sichere Darstellung enthalten.
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

# Schritt 2: Neu - Spielerpfeile fuer die Blickrichtung
PFEILE = {
    '10': '➡️',
    '-10': '⬅️',
    '0-1': '⬆️',
    '01': '⬇️',
    '00': '⏺️',
}


# Schritt 2: Neu - Richtung aus vX/vY als Schluessel erzeugen
def richtungs_key(spieler):
    return str(spieler['vX']) + str(spieler['vY'])


# Schritt 2: Geaendert - Spieler wird ueber das Spielfeld gerendert
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
```

### main.py

```python
from spielfeld import SPIELFELD, finde_startposition
from function import render

start = finde_startposition(SPIELFELD)

# Schritt 2: Neu - Spieler als Dictionary mit Position und Richtung
spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 0,
    'vY': 0,
}

# Didaktik: Richtung wird im main.py vor dem Rendern gesetzt.
spieler['vX'] = 1
spieler['vY'] = 0

outputClear()
# Schritt 2: Geaendert - Rendern mit separatem Spielerobjekt
render(SPIELFELD, spieler)
outputFlush()
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
