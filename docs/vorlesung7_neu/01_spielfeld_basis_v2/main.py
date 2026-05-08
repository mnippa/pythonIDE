from spielfeld import FELD, erstelle_spieler
from renderer import render, render_status

feld = [zeile[:] for zeile in FELD]
spieler = erstelle_spieler(feld)

steps = 0
turns = 0

outputClear()
render(feld, spieler)
render_status(steps, turns)
outputFlush()

print("Grunddarstellung des Spielfelds (v2 ohne Tupel).")
