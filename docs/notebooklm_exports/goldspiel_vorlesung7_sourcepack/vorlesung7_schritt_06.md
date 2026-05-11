# Projekt 45 - Schritt 06 (06_spielfelder_laden)

## Inhalte

### README.md

```markdown
# 06 Spielfelder Laden

In diesem Schritt werden Spielfelder aus Textdateien geladen.
Damit koennen feste, bewusst gewaehlte 9x9-Felder verwendet werden.

## Neu in Schritt 06

Im Vergleich zu Schritt 05 sind diese Punkte neu:

- Spielfelder kommen aus Dateien in einem Unterordner `spielfelder/`.
- Neue Ladefunktion `lade_spielfeld(dateiname)` mit Formatpruefung (9 Zeilen, 9 Zeichen, nur 0/1/7/9).
- Gold-Regel nach deiner Vorgabe:
  Es werden nur so viele zusaetzliche Zufalls-Goldfelder gesetzt, bis `gold_anzahl` erreicht ist.
  Bereits vorhandene Goldfelder (`7`) aus der Datei bleiben erhalten.
- Levelauswahl per Eingabe (`00`, `01`, `02`) beim Start.

## Dateiformat der Spielfelder

- Genau 9 Zeilen
- Pro Zeile genau 9 Zeichen
- Erlaubte Zeichen:
  - `0` = leer
  - `1` = Wand
  - `7` = Gold (bereits fest im Level)
  - `9` = Ziel

## Hinweis fuer den naechsten Schritt

Diese festen Felder sind die Basis fuer die kritische Pfadbetrachtung.
Fuer Monte-Carlo- bzw. Brute-Force-Ideen kann spaeter vorab eine optimale Schrittzahl
berechnet und am Ende ein optimaler Weg gerendert werden.
Die genaue Implementierung folgt dann separat.

## s6_spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 7 = Gold
# 9 = Ziel

import random


def lade_spielfeld(dateiname):
    zeilen = []

    with open(dateiname, 'r', encoding='utf-8') as datei:
        for roh in datei:
            text = roh.strip()
            if text != '':
                zeilen.append(text)

    if len(zeilen) != 9:
        raise Exception('Spielfeld muss genau 9 Zeilen haben: ' + dateiname)

    spielfeld = []
    for y in range(9):
        textzeile = zeilen[y]
        if len(textzeile) != 9:
            raise Exception('Zeile ist nicht 9 Zeichen lang in ' + dateiname + ' bei Zeile ' + str(y))

        zeile = []
        for x in range(9):
            zeichen = textzeile[x]
            if zeichen != '0' and zeichen != '1' and zeichen != '7' and zeichen != '9':
                raise Exception('Ungueltiges Zeichen im Spielfeld: ' + zeichen)
            zeile.append(int(zeichen))
        spielfeld.append(zeile)

    return spielfeld


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
```

## s6_functions.py

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


def render_status(spieler, level_name):
    print()
    print('Level: ' + level_name)
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + '/' + str(spieler['goldTotal']))
```

## main.py

```python
from s6_spielfeld import lade_spielfeld, platziere_gold_bis_anzahl, platziere_spieler, zaehle_goldfelder
from s6_functions import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

level_name = input('Level-Datei (00/01/02), Enter fuer 00: ').strip()
if level_name == '':
    level_name = '00'

level_datei = 'spielfelder/' + level_name + '.txt'

try:
    feld = lade_spielfeld(level_datei)
except Exception as fehler:
    print('Fehler beim Laden: ' + str(fehler))
    print('Nutze Standard-Level 00.')
    level_name = '00'
    level_datei = 'spielfelder/00.txt'
    feld = lade_spielfeld(level_datei)

gold_anzahl = 4
platziere_gold_bis_anzahl(feld, gold_anzahl)
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
    render_status(spieler, level_name)
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
```

### main.py

```python
from s6_spielfeld import lade_spielfeld, platziere_gold_bis_anzahl, platziere_spieler_mit_feldstart, zaehle_goldfelder
from s6_functions import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel

# Schritt 6: Levelauswahl und Laden aus dem Unterordner spielfelder
level_name = input('Level-Datei (00/01/02), Enter fuer 00: ').strip()
if level_name == '':
    level_name = '00'

level_datei = 'spielfelder/' + level_name + '.txt'

try:
    feld = lade_spielfeld(level_datei)
except Exception as fehler:
    print('Fehler beim Laden: ' + str(fehler))
    print('Nutze Standard-Level 00.')
    level_name = '00'
    level_datei = 'spielfelder/00.txt'
    feld = lade_spielfeld(level_datei)

# Schritt 6: Goldregel - nur bis zur Zielanzahl auffuellen
gold_anzahl = 4
platziere_gold_bis_anzahl(feld, gold_anzahl)
# Schritt 6: Neu - Startfeld (2) hat Vorrang, sonst zufaelliger Spawn
start = platziere_spieler_mit_feldstart(feld)

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
    # Schritt 6: Levelname wird im Status ausgegeben
    render_status(spieler, level_name)
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

### s6_functions.py

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


# Schritt 6: Statusanzeige zeigt zusaetzlich den geladenen Levelnamen
def render_status(spieler, level_name):
    print()
    print('Level: ' + level_name)
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen: ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + '/' + str(spieler['goldTotal']))
```

### s6_spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 7 = Gold
# 9 = Ziel

import random


# Schritt 6: Neues Laden von Spielfeldern aus Textdateien
def lade_spielfeld(dateiname):
    zeilen = []

    with open(dateiname, 'r', encoding='utf-8') as datei:
        for roh in datei:
            text = roh.strip()
            if text != '':
                zeilen.append(text)

    if len(zeilen) == 0:
        raise Exception('Spielfeld ist leer: ' + dateiname)

    breite = len(zeilen[0])
    if breite == 0:
        raise Exception('Spielfeld hat leere Zeile: ' + dateiname)

    hoehe = len(zeilen)
    if hoehe < 4 or breite < 4:
        raise Exception('Spielfeld muss mindestens 4x4 sein: ' + dateiname)

    spielfeld = []
    for y in range(hoehe):
        textzeile = zeilen[y]
        if len(textzeile) != breite:
            raise Exception('Zeile hat falsche Laenge in ' + dateiname + ' bei Zeile ' + str(y))

        zeile = []
        for x in range(breite):
            zeichen = textzeile[x]
            if zeichen != '0' and zeichen != '1' and zeichen != '2' and zeichen != '7' and zeichen != '9':
                raise Exception('Ungueltiges Zeichen im Spielfeld: ' + zeichen)
            zeile.append(int(zeichen))
        spielfeld.append(zeile)

    return spielfeld



# Schritt 6: Neu - Startfeld (2) im geladenen Spielfeld erkennen

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


# Schritt 6: Gold nur bis Zielanzahl auffuellen, vorhandenes Gold bleibt
def platziere_gold_bis_anzahl(spielfeld, gold_anzahl):
    bereits = zaehle_goldfelder(spielfeld)
    positionen = freie_positionen(spielfeld)

    while bereits < gold_anzahl and len(positionen) > 0:
        position = ziehe_zufaellige_position(positionen)
        spielfeld[position['y']][position['x']] = 7
        bereits = bereits + 1


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

# Schritt 6: Neu - Wenn Startfeld vorhanden, festen Spawn nehmen, sonst zufaellig

def platziere_spieler_mit_feldstart(spielfeld):
    start = finde_startposition(spielfeld)

    if start is not None:
        richtungen = [
            {'vX': 1, 'vY': 0},
            {'vX': -1, 'vY': 0},
            {'vX': 0, 'vY': -1},
            {'vX': 0, 'vY': 1},
        ]

        spielfeld[start['y']][start['x']] = 0
        richtung_index = random.randint(0, len(richtungen) - 1)
        richtung = richtungen[richtung_index]

        return {
            'x': start['x'],
            'y': start['y'],
            'vX': richtung['vX'],
            'vY': richtung['vY'],
        }

    return platziere_spieler(spielfeld)
```

### 00.txt

```text
111111111
100000001
100700001
100010001
100000001
100000001
100000001
100000091
111111111
```

### 01.txt

```text
111111111
100000001
101110001
100000001
100010001
100000001
100011101
107000091
111111111
```

### 02.txt

```text
111111111
100000001
100011001
100000001
101000101
100000001
100110001
100700091
111111111
```

### 03.txt

```text
111111111
100010001
101010101
107000701
101110101
100270001
101011101
107000091
111111111
```
