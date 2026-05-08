# Projekt 45 - Schritt 07 (07_kritischer_pfad)

## Inhalte

### README.md

```markdown
# 07 Kritischer Pfad (Optimaler Weg)

In diesem Schritt wird der optimale Weg automatisch berechnet.
Wichtig: Nur Vorwaertsschritte sind Kosten. Drehungen kosten 0.

## Neu in Schritt 07

- Automatische Berechnung des optimalen Weges vor Spielstart.
- **Goldmünzen-Anzahl:** Zu Spielstart wird die Anzahl der einzusammelnden Goldmünzen abgefragt (Default: 4).
- Zustand fuer die Suche: Position + eingesammeltes Gold.
- Kostenmodell: nur Vorwaertsbewegungen zaehlen als Schritte.
- **Pfad-Vergleich am Spielende:**
  - Der **eigene Pfad wird immer angezeigt** (mit Overlay-Richtungen).
  - Der **optimale Pfad wird nur angezeigt, wenn der eigene Weg suboptimal war** (mehr Schritte als das Optimum).
  - Dies vermeidet redundante Informationen und fokussiert das Lernen auf Verbesserungspotenzial.

## Grundidee des Algorithmus

Wir nutzen BFS auf einem Zustandsraum:

- Zustand: \`x\`, \`y\`, \`goldMask\`
- Kante: Bewegung auf ein Nachbarfeld (oben/unten/links/rechts), wenn keine Wand
- Kosten: 1 pro Bewegung

Warum ohne Richtung?
Da Drehungen kostenlos sind, kann man vor jedem Schritt frei ausrichten.
Damit reicht Position + Goldstatus fuer das Optimum in Schritten.

## Dateien

- \`s7_spielfeld.py\`: Laden, Gold-Auffuellen, Spielerplatzierung
- \`s7_functions.py\`: Rendern, Bewegung, Status, Optimalpfad-Overlay, **neu: \`render_eigener_pfad()\` für Eigenweg-Darstellung**
- \`s7_optimal.py\`: BFS fuer optimale Schrittzahl und Pfad
- \`main.py\`: Spielablauf, **neu: \`lese_gold_anzahl()\` für interaktive Gold-Eingabe**, Vergleich mit Optimum

## Hinweis

Die Monte-Carlo-Idee bleibt weiterhin als Vergleich interessant.
Dieser Schritt liefert aber eine exakte Referenz (optimale Schrittzahl).
- Pfad-Overlay: Startposition als \`👨\`, Richtungen als Pfeile, Doppelpfeile (\`↔️\`/\`↕️\`) bei Hin-und-zurueck-Bewegungen.
- Spawn-Regel: Enthaelt das geladene Spielfeld ein Startfeld (\`2\`), wird dort fix gespawnt; sonst bleibt der Spawn zufaellig.
- Fuer Level \`03\` werden bewusst schwierigere Startpositionen gewaehlt, damit der beste Weg weniger offensichtlich ist.

## Exkurs: Strategie zur Ermittlung des kritischen Pfads

### 1. Was bedeutet hier "kritischer Pfad"?

In diesem Projekt meinen wir damit:
- den Weg mit der kleinsten Anzahl an **Vorwaertsschritten**,
- der alle Goldmuenzen einsammelt,
- und danach das Ziel erreicht.

Drehungen (\`l\`/\`r\`) sind bewusst **kostenfrei** und gehen nicht in die Optimierung ein.

### 2. Warum ist "alles ausprobieren" schwierig?

Wenn man blind alle moeglichen Aktionsfolgen testet, waechst die Anzahl sehr schnell.
Das ist als Idee (Brute Force) gut zum Verstehen, aber ineffizient.

### 3. Leicht verstaendliche, exakte Strategie: BFS

Wir nutzen Breadth-First Search (BFS) auf einem **Zustandsraum**:
- Zustand = \`x\`, \`y\`, \`goldMask\`
  - \`x\`, \`y\`: aktuelle Position
  - \`goldMask\`: welche Goldfelder bereits eingesammelt wurden
- Uebergang = Bewegung zu einem benachbarten, nicht blockierten Feld
- Kosten = 1 pro Bewegung

Da BFS Ebene fuer Ebene sucht, ist der **erste gefundene Zielzustand** garantiert optimal.

### 4. Warum ohne Richtung im Zustand?

Richtung wird hier nicht als Kostenfaktor verwendet, weil Drehungen kostenlos sind.
Deshalb reicht fuer das optimale Schrittmass die Position plus Goldstatus.

### 5. Ergebnis der Berechnung

Die Suche liefert:
- \`optimum\`: minimale Schrittzahl
- \`pfad\`: den dazugehoerigen optimalen Weg

Im Spiel gilt danach:
- Der **eigene Pfad wird immer angezeigt** (was gespielt wurde).
- Der **optimale Pfad wird als Vergleich nur gezeigt, wenn der eigene Weg suboptimal war** (\`eigene_schritte > optimum\`).
- Wenn beide Pfade identisch sind (\`eigene_schritte == optimum\`), wurde optimal gespielt.

### 6. Bezug zu Monte-Carlo aus der Vorlesung

- Monte-Carlo: viele zufaellige Versuche, gute Naeherung moeglich
- BFS hier: exakte Referenz fuer das konkrete Spielfeld

Didaktisch ist die Kombination stark:
- erst Intuition/Heuristik durch Simulation,
- dann exakte Loesung als Vergleich.
```

### main.py

```python
from s7_spielfeld import lade_spielfeld, kopiere_spielfeld, platziere_gold_bis_anzahl, platziere_spieler_mit_feldstart, zaehle_goldfelder
from s7_functions import render, render_status, render_optimaler_pfad, render_eigener_pfad, drehe_links, drehe_rechts, gehe_vorwaerts, sammle_gold, ist_ziel
from s7_optimal import finde_optimalen_pfad


def lese_gold_anzahl():
    text = input('Goldmuenzen beim Start, Enter fuer 4: ').strip()
    if text == '':
        return 4

    try:
        gold_anzahl = int(text)
        if gold_anzahl < 0:
            raise ValueError()
        return gold_anzahl
    except Exception:
        print('Ungueltige Goldanzahl. Nutze 4.')
        return 4


# Schritt 7: Level laden wie in Schritt 6
level_name = input('Level-Datei (00/01/02/03), Enter fuer 03: ').strip()
if level_name == '':
    level_name = '03'

level_datei = 'spielfelder/' + level_name + '.txt'

try:
    feld_basis = lade_spielfeld(level_datei)
except Exception as fehler:
    print('Fehler beim Laden: ' + str(fehler))
    print('Nutze Standard-Level 03.')
    level_name = '03'
    level_datei = 'spielfelder/03.txt'
    feld_basis = lade_spielfeld(level_datei)

gold_anzahl = lese_gold_anzahl()

# Schritt 7: Vor dem Spiel Gold auffuellen und dann Optimum berechnen
feld_mit_gold = kopiere_spielfeld(feld_basis)
platziere_gold_bis_anzahl(feld_mit_gold, gold_anzahl)

# Schritt 7: Neu - Startfeld (2) hat Vorrang, sonst zufaelliger Spawn
start = platziere_spieler_mit_feldstart(feld_mit_gold)

start_opt = {
    'x': start['x'],
    'y': start['y'],
}

optimal = finde_optimalen_pfad(feld_mit_gold, start_opt)
optimum_schritte = optimal['optimum']
optimaler_pfad = optimal['pfad']

# Schritt 7: Separates Spielfeld fuer den echten Spielverlauf
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

# Schritt 7: Eigener Pfad wird fuer Vergleich mitgespeichert
eigener_pfad = [
    {'x': start['x'], 'y': start['y']}
]

while True:
    sammle_gold(feld, spieler)

    outputClear()
    render(feld, spieler)
    render_status(spieler, level_name, optimum_schritte)
    outputFlush()

    if ist_ziel(feld, spieler) and spieler['gold'] == spieler['goldTotal']:
        print('Gewonnen! Alle Goldmuenzen wurden eingesammelt.')

        eigener_ist_optimal = (optimum_schritte >= 0 and spieler['steps'] == optimum_schritte)
        zeige_optimal = (optimum_schritte >= 0 and not eigener_ist_optimal)

        if eigener_ist_optimal:
            print('Perfekt! Du hast den optimalen Weg getroffen.')
        elif zeige_optimal:
            print('Dein Weg war nicht optimal.')
            print('Deine Schritte: ' + str(spieler['steps']))
            print('Optimale Schritte: ' + str(optimum_schritte))

        input('Enter fuer Pfadanzeige...')

        outputClear()
        print('Dein Pfad (Start = 👨, Doppelpfeile bei Hin-und-zurueck):')
        render_eigener_pfad(feld_mit_gold, eigener_pfad, start)

        if zeige_optimal:
            print()
            print('Optimaler Pfad (Start = 👨, Doppelpfeile bei Hin-und-zurueck):')
            render_optimaler_pfad(feld_mit_gold, optimaler_pfad, start)

        outputFlush()
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
            # Schritt 7: Nur Vorwaertsbewegung zaehlt als Kosten
            spieler['steps'] = spieler['steps'] + 1
            eigener_pfad.append({'x': spieler['posX'], 'y': spieler['posY']})
```

### s7_functions.py

```python
SYMBOLE = {
    0: '⬜',
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


# Schritt 7: Neu - Gemeinsames Overlay fuer eigenen und optimalen Pfad

def _render_pfad_overlay(spielfeld, pfad, start):
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
        print(ausgabe)


def render_optimaler_pfad(spielfeld, pfad, start):
    _render_pfad_overlay(spielfeld, pfad, start)


def render_eigener_pfad(spielfeld, pfad, start):
    _render_pfad_overlay(spielfeld, pfad, start)


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


# Schritt 7: Geaendert - Status zeigt auch berechnetes Optimum (nur Schritte)

def render_status(spieler, level_name, optimum):
    print()
    print('Level: ' + level_name)
    print('Position: x=' + str(spieler['posX']) + ' y=' + str(spieler['posY']))
    print('Richtung: vX=' + str(spieler['vX']) + ' vY=' + str(spieler['vY']))
    print('Schritte: ' + str(spieler['steps']))
    print('Drehungen (ohne Kosten): ' + str(spieler['turns']))
    print('Gold: ' + str(spieler['gold']) + '/' + str(spieler['goldTotal']))
    print('Optimum (nur Schritte): ' + str(optimum))
```

### s7_optimal.py

```python
from collections import deque


# Schritt 7: Neu - Goldpositionen fuer Bitmaske sammeln

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


# Schritt 7: Neu - BFS ueber (x,y,goldMask), Kosten nur fuer Vorwaertsschritte

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

### s7_spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 7 = Gold
# 9 = Ziel

import random


# Schritt 7: Uebernommen aus Schritt 6, damit feste Level geladen werden koennen

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


# Schritt 7: Neu - Kopie fuer getrennte Nutzung (Gameplay vs. Optimalpfad-Berechnung)

def kopiere_spielfeld(spielfeld):
    kopie = []
    height = len(spielfeld)

    for y in range(height):
        kopie.append(spielfeld[y][:])

    return kopie



# Schritt 7: Neu - Startfeld (2) im geladenen Spielfeld erkennen

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


# Schritt 7: Uebernommen - vorhandenes Gold bleibt, nur bis Zielanzahl auffuellen

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

# Schritt 7: Alt - Levelspezifischer Spawn (nicht mehr genutzt)

def platziere_spieler_fuer_level(spielfeld, level_name):
    richtungen = [
        {'vX': 1, 'vY': 0},
        {'vX': -1, 'vY': 0},
        {'vX': 0, 'vY': -1},
        {'vX': 0, 'vY': 1},
    ]

    if level_name != '03':
        return platziere_spieler(spielfeld)

    kandidaten = [
        {'x': 3, 'y': 1},
        {'x': 5, 'y': 1},
        {'x': 3, 'y': 5},
        {'x': 5, 'y': 5},
    ]

    gueltig = []
    anzahl = len(kandidaten)
    for i in range(anzahl):
        x = kandidaten[i]['x']
        y = kandidaten[i]['y']
        if spielfeld[y][x] == 0:
            gueltig.append({'x': x, 'y': y})

    if len(gueltig) == 0:
        return platziere_spieler(spielfeld)

    index = random.randint(0, len(gueltig) - 1)
    position = gueltig[index]

    richtung_index = random.randint(0, len(richtungen) - 1)
    richtung = richtungen[richtung_index]

    return {
        'x': position['x'],
        'y': position['y'],
        'vX': richtung['vX'],
        'vY': richtung['vY'],
    }

# Schritt 7: Neu - Wenn Startfeld vorhanden, festen Spawn nehmen, sonst zufaellig

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
