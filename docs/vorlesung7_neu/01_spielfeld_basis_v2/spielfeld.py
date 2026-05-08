WAND = 1
LEER = 0
START = 2
ZIEL = 9
GOLD = 7

FELD = [
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


def finde_start(feld):
    for y, zeile in enumerate(feld):
        for x, wert in enumerate(zeile):
            if wert == START:
                return [x, y]
    return [1, 1]


def erstelle_spieler(feld):
    return {
        "position": finde_start(feld),
        "richtung": "rechts",
    }
