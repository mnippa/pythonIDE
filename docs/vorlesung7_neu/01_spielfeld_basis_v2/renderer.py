from spielfeld import LEER, WAND, START, ZIEL, GOLD

SYMBOLE = {
    LEER: "  ",
    WAND: "⬛",
    START: "🟦",
    ZIEL: "🚪",
    GOLD: "🪙",
}

PFEILE = {
    "rechts": "➡️",
    "links": "⬅️",
    "oben": "⬆️",
    "unten": "⬇️",
}


def render(feld, spieler):
    hoehe = len(feld)
    breite = len(feld[0])

    sx = spieler["position"][0]
    sy = spieler["position"][1]
    richtung = spieler["richtung"]

    for y in range(hoehe):
        ausgabe = ""
        for x in range(breite):
            if x == sx and y == sy:
                ausgabe += PFEILE.get(richtung, "❓")
            else:
                ausgabe += SYMBOLE.get(feld[y][x], "??")
        print(ausgabe)


def render_status(steps, turns):
    print()
    print(f"Schritte:  {steps}")
    print(f"Drehungen: {turns}")
