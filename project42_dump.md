# Project 42 Dump

Project: 42 | Vorlesung VII | type=python | created=2026-05-06 17:55:42

## FILE: init.py

```
# Dein Python-Projekt

# Hier kannst du mit Python loslegen!
print('Hallo Welt!')

```

## FILE: README.md

```
# Block 2 – Skriptpaket

Dieses ZIP enthält alle Skripte für Block 2 in sinnvollen Teilschritten.

## Reihenfolge
1. Spielfeld-Basis
2. Bewegung und Steuerung
3. Goldspiel mit festen Positionen
4. Goldspiel mit Zufallsplatzierung
5. Labyrinth-Demo: Rechte-Hand-Regel
6. Labyrinth-Demo: Mit-Merken-Variante
7. IDEGUI: Taschenrechner (Run Driven)
8. IDEGUI: Taschenrechner (Event Driven)
9. IDEGUI: Münzspiel-Demo

## Hinweise
- Die Konsolen/Labyrinth-Demos enthalten `outputClear()`, `outputFlush()` und `sleep()`.
- Die IDEGUI-Beispiele bestehen aus `index.html`, `style.css`, `init.py`.
- Das Münzspiel in IDEGUI ist als Demo gedacht: GUI-Seite zeigen, nicht vollständig im Detail analysieren.

```

## FILE: 01_spielfeld_basis/main.py

```
from spielfeld import FELD, START_POS, START_RICHTUNG
from renderer import render, render_status
import time

feld = [zeile[:] for zeile in FELD]
spieler_pos = START_POS
spieler_richtung = START_RICHTUNG

outputClear()
render(feld, spieler_pos, spieler_richtung)
render_status(0, 0)
outputFlush()
time.sleep(0.5)
print("Grunddarstellung des Spielfelds.")

```

## FILE: 01_spielfeld_basis/README.md

```
# 01 Spielfeld-Basis

Nur Datenmodell, Spielerzustand und Darstellung.

```

## FILE: 01_spielfeld_basis/renderer.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    7: "🪙",
    9: "🚪",
}

PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}

def render(feld, spieler_pos, spieler_richtung):
    breite = len(feld[0])
    hoehe = len(feld)

    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == spieler_pos:
                ausgabe += PFEILE[spieler_richtung]
            else:
                wert = feld[y][x]
                ausgabe += SYMBOLE[wert]
        print(ausgabe)

def render_status(steps: int, turns: int, extra: str = ""):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")
    if extra:
        print(extra)

```

## FILE: 01_spielfeld_basis/spielfeld.py

```
FELD = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,0,0,9,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,1,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,1,1]
]

START_POS = (1, 1)
START_RICHTUNG = (1, 0)

```

## FILE: 02_bewegung_und_steuerung/main.py

```
import time
from spielfeld import FELD, START_POS, START_RICHTUNG
from movement import schritt, drehe_links, drehe_rechts, ist_wand, ist_ziel
from renderer import render, render_status

feld = [zeile[:] for zeile in FELD]
spieler_pos = START_POS
spieler_richtung = START_RICHTUNG
steps = 0
turns = 0

while True:
    outputClear()
    render(feld, spieler_pos, spieler_richtung)
    render_status(steps, turns)
    outputFlush()

    if ist_ziel(feld, spieler_pos):
        print("\nZiel erreicht!")
        break

    cmd = input("l / r / Enter: ").strip().lower()

    if cmd == "l":
        spieler_richtung = drehe_links(spieler_richtung)
        turns += 1
    elif cmd == "r":
        spieler_richtung = drehe_rechts(spieler_richtung)
        turns += 1
    else:
        next_pos = schritt(spieler_pos, spieler_richtung)
        if ist_wand(feld, next_pos):
            print("Dort ist eine Wand.")
            time.sleep(0.6)
        else:
            spieler_pos = next_pos
            steps += 1
```

## FILE: 02_bewegung_und_steuerung/movement.py

```
def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_links(richtung):
    dx, dy = richtung
    return (dy, -dx)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def lies_feld(feld, pos):
    x, y = pos
    return feld[y][x]

def ist_wand(feld, pos):
    return lies_feld(feld, pos) == 1

def ist_ziel(feld, pos):
    return lies_feld(feld, pos) == 9

def sammle_gold(feld, pos):
    x, y = pos
    if feld[y][x] == 7:
        feld[y][x] = 0
        return True
    return False

```

## FILE: 02_bewegung_und_steuerung/README.md

```
# 02 Bewegung und Steuerung

Manuelle Steuerung mit `l`, `r` und Enter.

```

## FILE: 02_bewegung_und_steuerung/renderer.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    7: "🪙",
    9: "🚪",
}

PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}

def render(feld, spieler_pos, spieler_richtung):
    breite = len(feld[0])
    hoehe = len(feld)

    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == spieler_pos:
                ausgabe += PFEILE[spieler_richtung]
            else:
                wert = feld[y][x]
                ausgabe += SYMBOLE[wert]
        print(ausgabe)

def render_status(steps: int, turns: int, extra: str = ""):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")
    if extra:
        print(extra)

```

## FILE: 02_bewegung_und_steuerung/spielfeld.py

```
FELD = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,0,0,9,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,1,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,1,1]
]
START_POS = (1, 1)
START_RICHTUNG = (1, 0)

```

## FILE: 03_goldspiel_feste_positionen/main.py

```
from spielfeld import FELD, START_POS, START_RICHTUNG, GOLD_GESAMT
from movement import schritt, drehe_links, drehe_rechts, ist_wand, ist_ziel, sammle_gold
from renderer import render, render_status
import time

feld = [zeile[:] for zeile in FELD]
spieler_pos = START_POS
spieler_richtung = START_RICHTUNG
steps = 0
turns = 0
gold_gefunden = 0

while True:
    outputClear()
    render(feld, spieler_pos, spieler_richtung)
    render_status(steps, turns, gold_gefunden, GOLD_GESAMT)
    outputFlush()

    if sammle_gold(feld, spieler_pos):
        gold_gefunden += 1

    if ist_ziel(feld, spieler_pos) and gold_gefunden == GOLD_GESAMT:
        print("\nGewonnen! Alle Goldmünzen gesammelt.")
        break

    cmd = input("l / r / Enter: ").strip().lower()

    if cmd == "l":
        spieler_richtung = drehe_links(spieler_richtung)
        turns += 1
    elif cmd == "r":
        spieler_richtung = drehe_rechts(spieler_richtung)
        turns += 1
    else:
        next_pos = schritt(spieler_pos, spieler_richtung)
        if ist_wand(feld, next_pos):
            print("Dort ist eine Wand.")
            time.sleep(0.6)
        else:
            spieler_pos = next_pos
            steps += 1

```

## FILE: 03_goldspiel_feste_positionen/movement.py

```
def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_links(richtung):
    dx, dy = richtung
    return (dy, -dx)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def lies_feld(feld, pos):
    x, y = pos
    return feld[y][x]

def ist_wand(feld, pos):
    return lies_feld(feld, pos) == 1

def ist_ziel(feld, pos):
    return lies_feld(feld, pos) == 9

def sammle_gold(feld, pos):
    x, y = pos
    if feld[y][x] == 7:
        feld[y][x] = 0
        return True
    return False

```

## FILE: 03_goldspiel_feste_positionen/README.md

```
# 03 Goldspiel mit festen Positionen

Vier Goldmünzen liegen fest. Ziel ist erst nach dem Einsammeln sinnvoll erreichbar.

```

## FILE: 03_goldspiel_feste_positionen/renderer.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    7: "🪙",
    9: "🚪",
}
PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}
def render(feld, spieler_pos, spieler_richtung):
    breite = len(feld[0])
    hoehe = len(feld)
    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == spieler_pos:
                ausgabe += PFEILE[spieler_richtung]
            else:
                ausgabe += SYMBOLE[feld[y][x]]
        print(ausgabe)

def render_status(steps: int, turns: int, gold_gefunden: int, gold_gesamt: int):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")
    print(f"Gold:      {gold_gefunden}/{gold_gesamt}")

```

## FILE: 03_goldspiel_feste_positionen/spielfeld.py

```
FELD = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,7,0,0,0,9,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,7,0,0,0,7,0,1],
    [1,0,0,0,1,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,7,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,1,1]
]
START_POS = (1, 1)
START_RICHTUNG = (1, 0)
GOLD_GESAMT = 4

```

## FILE: 04_goldspiel_zufallsplatzierung/main.py

```
from spielfeld import GRUND_FELD, GOLD_GESAMT
from movement import schritt, drehe_links, drehe_rechts, ist_wand, ist_ziel, sammle_gold
from random_setup import platziere_gold, platziere_spieler
from renderer import render, render_status
import time

feld = [zeile[:] for zeile in GRUND_FELD]
for _ in range(GOLD_GESAMT):
    platziere_gold(feld)
spieler_pos, spieler_richtung = platziere_spieler(feld)

steps = 0
turns = 0
gold_gefunden = 0

while True:
    outputClear()
    render(feld, spieler_pos, spieler_richtung)
    render_status(steps, turns, gold_gefunden, GOLD_GESAMT)
    outputFlush()

    if sammle_gold(feld, spieler_pos):
        gold_gefunden += 1

    if ist_ziel(feld, spieler_pos) and gold_gefunden == GOLD_GESAMT:
        print("\nGewonnen! Alle Goldmünzen gesammelt.")
        break

    cmd = input("l / r / Enter: ").strip().lower()

    if cmd == "l":
        spieler_richtung = drehe_links(spieler_richtung)
        turns += 1
    elif cmd == "r":
        spieler_richtung = drehe_rechts(spieler_richtung)
        turns += 1
    else:
        next_pos = schritt(spieler_pos, spieler_richtung)
        if ist_wand(feld, next_pos):
            print("Dort ist eine Wand.")
            time.sleep(0.6)
        else:
            spieler_pos = next_pos
            steps += 1

```

## FILE: 04_goldspiel_zufallsplatzierung/movement.py

```
def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_links(richtung):
    dx, dy = richtung
    return (dy, -dx)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def lies_feld(feld, pos):
    x, y = pos
    return feld[y][x]

def ist_wand(feld, pos):
    return lies_feld(feld, pos) == 1

def ist_ziel(feld, pos):
    return lies_feld(feld, pos) == 9

def sammle_gold(feld, pos):
    x, y = pos
    if feld[y][x] == 7:
        feld[y][x] = 0
        return True
    return False

```

## FILE: 04_goldspiel_zufallsplatzierung/random_setup.py

```
import random

def zufalls_richtung():
    return random.choice([(1, 0), (-1, 0), (0, 1), (0, -1)])

def platziere_gold(feld):
    breite = len(feld[0])
    hoehe = len(feld)
    while True:
        x = random.randint(0, breite - 1)
        y = random.randint(0, hoehe - 1)
        if feld[y][x] == 0:
            feld[y][x] = 7
            return (x, y)

def platziere_spieler(feld):
    breite = len(feld[0])
    hoehe = len(feld)
    while True:
        x = random.randint(0, breite - 1)
        y = random.randint(0, hoehe - 1)
        if feld[y][x] == 0:
            return (x, y), zufalls_richtung()

```

## FILE: 04_goldspiel_zufallsplatzierung/README.md

```
# 04 Goldspiel mit Zufallsplatzierung

Gold und Spieler werden zufällig auf freie Felder gesetzt.

```

## FILE: 04_goldspiel_zufallsplatzierung/renderer.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    7: "🪙",
    9: "🚪",
}
PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}
def render(feld, spieler_pos, spieler_richtung):
    breite = len(feld[0])
    hoehe = len(feld)
    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == spieler_pos:
                ausgabe += PFEILE[spieler_richtung]
            else:
                ausgabe += SYMBOLE[feld[y][x]]
        print(ausgabe)

def render_status(steps: int, turns: int, gold_gefunden: int, gold_gesamt: int):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")
    print(f"Gold:      {gold_gefunden}/{gold_gesamt}")

```

## FILE: 04_goldspiel_zufallsplatzierung/spielfeld.py

```
GRUND_FELD = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,0,0,9,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,1,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,0,0,0,0,0,0,0,1],
    [1,1,1,1,1,1,1,1,1]
]
GOLD_GESAMT = 4

```

## FILE: 05_labyrinth_rechte_hand_demo/labyrinth_algorithms.py

```
def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def lies_feld(feld, pos):
    x, y = pos
    return feld[y][x]

def ist_wand(feld, pos):
    return lies_feld(feld, pos) == 1

def ist_ziel(feld, pos):
    return lies_feld(feld, pos) == 9

def naechster_schritt_rechte_hand(feld, pos, richtung):
    rechts = drehe_rechts(richtung)
    next_right = schritt(pos, rechts)

    if not ist_wand(feld, next_right):
        return next_right, rechts, False, True

    next_forward = schritt(pos, richtung)
    if not ist_wand(feld, next_forward):
        return next_forward, richtung, True, False

    return pos, rechts, False, True

```

## FILE: 05_labyrinth_rechte_hand_demo/labyrinth_demo.py

```
LABYRINTH = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,1,0,0,1],
    [1,0,1,1,0,1,0,1,1],
    [1,0,0,1,0,0,0,0,1],
    [1,1,0,1,1,1,1,0,1],
    [1,0,0,0,0,0,1,0,1],
    [1,0,1,1,1,0,1,0,1],
    [1,0,0,0,1,0,0,9,1],
    [1,1,1,1,1,1,1,1,1]
]

START_POS = (1, 1)
START_RICHTUNG = (1, 0)

```

## FILE: 05_labyrinth_rechte_hand_demo/labyrinth_view.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    9: "🚪",
}
PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}
def render(feld, pos, richtung):
    breite = len(feld[0])
    hoehe = len(feld)
    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == pos:
                ausgabe += PFEILE[richtung]
            else:
                ausgabe += SYMBOLE[feld[y][x]]
        print(ausgabe)

def render_status(steps, turns):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")

```

## FILE: 05_labyrinth_rechte_hand_demo/main.py

```
from labyrinth_demo import LABYRINTH, START_POS, START_RICHTUNG
from labyrinth_view import render, render_status
from labyrinth_algorithms import ist_ziel, naechster_schritt_rechte_hand
import time

pos = START_POS
richtung = START_RICHTUNG
steps = 0
turns = 0

while not ist_ziel(LABYRINTH, pos):
    outputClear()
    render(LABYRINTH, pos, richtung)
    render_status(steps, turns)
    outputFlush()
    time.sleep(0.25)

    neue_pos, neue_richtung, moved, turned = naechster_schritt_rechte_hand(LABYRINTH, pos, richtung)
    pos = neue_pos
    richtung = neue_richtung
    if moved:
        steps += 1
    if turned:
        turns += 1

outputClear()
render(LABYRINTH, pos, richtung)
render_status(steps, turns)
outputFlush()
print("\nZiel erreicht mit Rechte-Hand-Regel.")

```

## FILE: 05_labyrinth_rechte_hand_demo/README.md

```
# 05 Labyrinth-Demo – Rechte-Hand-Regel

9x9-Labyrinth mit Sackgassen. Vollständige Vorführ-Demo mit `outputClear()`, `outputFlush()` und `sleep()`.

```

## FILE: 06_labyrinth_mit_merken_demo/labyrinth_algorithms.py

```
def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def drehe_links(richtung):
    dx, dy = richtung
    return (dy, -dx)

def lies_feld(feld, pos):
    x, y = pos
    return feld[y][x]

def ist_wand(feld, pos):
    return lies_feld(feld, pos) == 1

def ist_ziel(feld, pos):
    return lies_feld(feld, pos) == 9

def kandidaten(richtung):
    # rechts, vorne, links, zurück
    rechts = drehe_rechts(richtung)
    links = drehe_links(richtung)
    zurueck = drehe_rechts(rechts)
    return [rechts, richtung, links, zurueck]

```

## FILE: 06_labyrinth_mit_merken_demo/labyrinth_demo.py

```
LABYRINTH = [
    [1,1,1,1,1,1,1,1,1],
    [1,0,0,0,0,1,0,0,1],
    [1,0,1,1,0,1,0,1,1],
    [1,0,0,1,0,0,0,0,1],
    [1,1,0,1,1,1,1,0,1],
    [1,0,0,0,0,0,1,0,1],
    [1,0,1,1,1,0,1,0,1],
    [1,0,0,0,1,0,0,9,1],
    [1,1,1,1,1,1,1,1,1]
]

START_POS = (1, 1)
START_RICHTUNG = (1, 0)

```

## FILE: 06_labyrinth_mit_merken_demo/labyrinth_view.py

```
SYMBOLE = {
    0: "  ",
    1: "⬛",
    9: "🚪",
}
PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}
def render(feld, pos, richtung, visited=None):
    visited = visited or set()
    breite = len(feld[0])
    hoehe = len(feld)
    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if (x, y) == pos:
                ausgabe += PFEILE[richtung]
            elif (x, y) in visited and feld[y][x] == 0:
                ausgabe += "· "
            else:
                ausgabe += SYMBOLE[feld[y][x]]
        print(ausgabe)

def render_status(steps, turns, visited_count):
    print()
    print(f"Schritte:        {steps}")
    print(f"Drehungen:       {turns}")
    print(f"Besuchte Felder: {visited_count}")

```

## FILE: 06_labyrinth_mit_merken_demo/main.py

```
from labyrinth_demo import LABYRINTH, START_POS, START_RICHTUNG
from labyrinth_view import render, render_status
from labyrinth_algorithms import schritt, ist_wand, ist_ziel, kandidaten
import time

pos = START_POS
richtung = START_RICHTUNG
visited = {pos}
steps = 0
turns = 0

while not ist_ziel(LABYRINTH, pos):
    outputClear()
    render(LABYRINTH, pos, richtung, visited)
    render_status(steps, turns, len(visited))
    outputFlush()
    time.sleep(0.25)

    moegliche = kandidaten(richtung)

    next_choice = None
    next_dir = richtung

    # Erst unbesuchte Felder bevorzugen
    for d in moegliche:
        next_pos = schritt(pos, d)
        if not ist_wand(LABYRINTH, next_pos) and next_pos not in visited:
            next_choice = next_pos
            next_dir = d
            break

    # Falls nichts unbesucht frei ist: irgendeinen freien Weg nehmen
    if next_choice is None:
        for d in moegliche:
            next_pos = schritt(pos, d)
            if not ist_wand(LABYRINTH, next_pos):
                next_choice = next_pos
                next_dir = d
                break

    if next_choice is None:
        break

    if next_dir != richtung:
        turns += 1

    richtung = next_dir
    pos = next_choice
    visited.add(pos)
    steps += 1

outputClear()
render(LABYRINTH, pos, richtung, visited)
render_status(steps, turns, len(visited))
outputFlush()
print("\nZiel erreicht mit Merken-Variante.")

```

## FILE: 06_labyrinth_mit_merken_demo/README.md

```
# 06 Labyrinth-Demo – Mit Merken

Variante mit `visited`, damit besuchte Felder vermieden bzw. sichtbar gemacht werden.

```

## FILE: 07_idegui_taschenrechner_run_driven/index.html

```
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Taschenrechner – Run Driven</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Taschenrechner – Run Driven</h1>

        <input type="number" data-element="a" placeholder="Zahl A">
        <input type="number" data-element="b" placeholder="Zahl B">

        <button data-run="true" name="add" value="add">Addieren</button>

        <div class="result">
            <strong>Ergebnis:</strong> <span data-element="result">-</span>
        </div>
        <p data-element="message">Gib zwei Zahlen ein.</p>
    </div>
</body>
</html>

```

## FILE: 07_idegui_taschenrechner_run_driven/init.py

```
import idegui as ui

a_str = ui.get("a")
b_str = ui.get("b")

try:
    a = float(a_str) if a_str else 0
    b = float(b_str) if b_str else 0
    result = a + b

    ui.set("result", str(result))
    ui.set("message", f"✓ {a} + {b} = {result}")
except ValueError:
    ui.set("message", "❌ Ungültige Eingabe.")

```

## FILE: 07_idegui_taschenrechner_run_driven/README.md

```
# 07 IDEGUI – Taschenrechner (Run Driven)

Vollständige Minimalstruktur:
- index.html
- style.css
- init.py

Konzept: gesamtes Skript läuft neu.

```

## FILE: 07_idegui_taschenrechner_run_driven/style.css

```
body {
    font-family: system-ui, sans-serif;
    background: linear-gradient(135deg, #eef2ff, #dbeafe);
    margin: 0;
    padding: 24px;
}
.container {
    max-width: 560px;
    margin: 0 auto;
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}
input, button {
    display: block;
    width: 100%;
    margin: 10px 0;
    padding: 12px;
    font-size: 16px;
}
button {
    background: #4f46e5;
    color: white;
    border: none;
    border-radius: 8px;
}
.result {
    margin-top: 16px;
}

```

## FILE: 08_idegui_taschenrechner_event_driven/index.html

```
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Taschenrechner – Event Driven</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Taschenrechner – Event Driven</h1>

        <input type="number" data-element="a" placeholder="Zahl A">
        <input type="number" data-element="b" placeholder="Zahl B">

        <div class="buttons">
            <button data-function="add" name="add" value="+">+</button>
            <button data-function="subtract" name="subtract" value="-">-</button>
            <button data-function="reset" name="reset" value="0">Reset</button>
        </div>

        <div class="result">
            <strong>Ergebnis:</strong> <span data-element="result">0</span>
        </div>
        <p data-element="message">Bereit.</p>
    </div>
</body>
</html>

```

## FILE: 08_idegui_taschenrechner_event_driven/init.py

```
import idegui as ui

def _read_numbers():
    a_str = ui.get("a")
    b_str = ui.get("b")
    a = float(a_str) if a_str else 0
    b = float(b_str) if b_str else 0
    return a, b

def add(trigger):
    try:
        a, b = _read_numbers()
        result = a + b
        ui.set("result", str(result))
        ui.set("message", f"✓ {a} + {b} = {result}")
    except ValueError:
        ui.set("message", "❌ Ungültige Eingabe.")

def subtract(trigger):
    try:
        a, b = _read_numbers()
        result = a - b
        ui.set("result", str(result))
        ui.set("message", f"✓ {a} - {b} = {result}")
    except ValueError:
        ui.set("message", "❌ Ungültige Eingabe.")

def reset(trigger):
    ui.set("a", "")
    ui.set("b", "")
    ui.set("result", "0")
    ui.set("message", "Zurückgesetzt.")

ui.set("result", "0")
ui.set("message", "Bereit.")

```

## FILE: 08_idegui_taschenrechner_event_driven/README.md

```
# 08 IDEGUI – Taschenrechner (Event Driven)

Hier werden einzelne Funktionen über Buttons ausgelöst.

```

## FILE: 08_idegui_taschenrechner_event_driven/style.css

```
body {
    font-family: system-ui, sans-serif;
    background: linear-gradient(135deg, #ecfeff, #d1fae5);
    margin: 0;
    padding: 24px;
}
.container {
    max-width: 560px;
    margin: 0 auto;
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}
input {
    display: block;
    width: 100%;
    margin: 10px 0;
    padding: 12px;
    font-size: 16px;
}
.buttons {
    display: flex;
    gap: 10px;
}
button {
    flex: 1;
    padding: 12px;
    font-size: 18px;
    background: #059669;
    color: white;
    border: none;
    border-radius: 8px;
}
.result {
    margin-top: 16px;
}

```

## FILE: 09_idegui_muenzspiel_demo/index.html

```
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Münzspiel – IDEGUI Demo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Münzspiel – IDEGUI Demo</h1>

        <div class="controls">
            <button data-function="turn_left" name="turn_left" value="left">↺ Links</button>
            <button data-function="forward" name="forward" value="forward">↑ Vor</button>
            <button data-function="turn_right" name="turn_right" value="right">↻ Rechts</button>
            <button data-function="reset_game" name="reset_game" value="reset">Reset</button>
        </div>

        <pre data-element="board" class="board"></pre>

        <div class="status">
            <p><strong>Schritte:</strong> <span data-element="steps">0</span></p>
            <p><strong>Drehungen:</strong> <span data-element="turns">0</span></p>
            <p><strong>Gold:</strong> <span data-element="gold">0/2</span></p>
            <p data-element="message">Willkommen!</p>
        </div>
    </div>
</body>
</html>

```

## FILE: 09_idegui_muenzspiel_demo/init.py

```
import idegui as ui

if 'GAME' not in globals():
    GAME = {}

def initial_board():
    return [
        [1,1,1,1,1,1,1],
        [1,0,0,7,0,9,1],
        [1,0,1,0,0,0,1],
        [1,0,0,0,1,0,1],
        [1,7,1,0,0,0,1],
        [1,0,0,0,0,0,1],
        [1,1,1,1,1,1,1],
    ]

PFEILE = {
    (1, 0): "➡️",
    (-1, 0): "⬅️",
    (0, -1): "⬆️",
    (0, 1): "⬇️",
}
SYMBOLE = {
    0: "  ",
    1: "⬛",
    7: "🪙",
    9: "🚪",
}

def reset_state():
    global GAME
    GAME = {
        "board": initial_board(),
        "pos": (1, 1),
        "dir": (1, 0),
        "steps": 0,
        "turns": 0,
        "gold_found": 0,
        "gold_total": 2,
        "message": "Willkommen!"
    }

def schritt(pos, richtung):
    x, y = pos
    dx, dy = richtung
    return (x + dx, y + dy)

def drehe_links(richtung):
    dx, dy = richtung
    return (dy, -dx)

def drehe_rechts(richtung):
    dx, dy = richtung
    return (-dy, dx)

def lies_feld(board, pos):
    x, y = pos
    return board[y][x]

def ist_wand(board, pos):
    return lies_feld(board, pos) == 1

def ist_ziel(board, pos):
    return lies_feld(board, pos) == 9

def sammle_gold():
    x, y = GAME["pos"]
    if GAME["board"][y][x] == 7:
        GAME["board"][y][x] = 0
        GAME["gold_found"] += 1
        GAME["message"] = "🪙 Gold eingesammelt!"

def render_board():
    lines = []
    board = GAME["board"]
    pos = GAME["pos"]
    direction = GAME["dir"]

    for y in range(len(board)):
        line = ""
        for x in range(len(board[0])):
            if (x, y) == pos:
                line += PFEILE[direction]
            else:
                line += SYMBOLE[board[y][x]]
        lines.append(line)
    ui.set("board", "\n".join(lines))

def render_status():
    ui.set("steps", str(GAME["steps"]))
    ui.set("turns", str(GAME["turns"]))
    ui.set("gold", f"{GAME['gold_found']}/{GAME['gold_total']}")
    ui.set("message", GAME["message"])

def redraw():
    render_board()
    render_status()

def turn_left(trigger):
    GAME["dir"] = drehe_links(GAME["dir"])
    GAME["turns"] += 1
    GAME["message"] = "↺ Nach links gedreht."
    redraw()

def turn_right(trigger):
    GAME["dir"] = drehe_rechts(GAME["dir"])
    GAME["turns"] += 1
    GAME["message"] = "↻ Nach rechts gedreht."
    redraw()

def forward(trigger):
    next_pos = schritt(GAME["pos"], GAME["dir"])
    if ist_wand(GAME["board"], next_pos):
        GAME["message"] = "⬛ Dort ist eine Wand."
        redraw()
        return

    GAME["pos"] = next_pos
    GAME["steps"] += 1
    sammle_gold()

    if ist_ziel(GAME["board"], GAME["pos"]):
        if GAME["gold_found"] == GAME["gold_total"]:
            GAME["message"] = "🎉 Gewonnen! Alle Münzen eingesammelt."
        else:
            GAME["message"] = "🚪 Ziel erreicht, aber noch nicht alle Münzen gesammelt."
    redraw()

def reset_game(trigger):
    reset_state()
    redraw()

# Initialisierung
if not GAME:
    reset_state()
redraw()

```

## FILE: 09_idegui_muenzspiel_demo/README.md

```
# 09 IDEGUI – Münzspiel-Demo

GUI-Demo für das Münzspiel:
- Buttons: links, rechts, vor
- Spielfeldanzeige als Text/Emoji
- globaler Zustand bleibt zwischen Events erhalten

```

## FILE: 09_idegui_muenzspiel_demo/style.css

```
body {
    font-family: system-ui, sans-serif;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    margin: 0;
    padding: 24px;
}
.container {
    max-width: 760px;
    margin: 0 auto;
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(0,0,0,0.12);
}
.controls {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
button {
    padding: 12px;
    font-size: 16px;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: white;
}
.board {
    background: #111827;
    color: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    min-height: 180px;
    font-size: 18px;
    line-height: 1.4;
}
.status {
    margin-top: 16px;
}

```

