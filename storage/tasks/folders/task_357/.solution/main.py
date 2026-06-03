from spielfeld import SPIELFELD, START_POS, GOAL_POS, ANALYSE
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, ist_ziel

spieler = {
    'posX': START_POS['x'],
    'posY': START_POS['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
}

while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler, GOAL_POS, ANALYSE)
    outputFlush()

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
        moved = gehe_vorwaerts(SPIELFELD, spieler)
        if moved:
            spieler['steps'] = spieler['steps'] + 1

    if ist_ziel(SPIELFELD, spieler):
        outputClear()
        render(SPIELFELD, spieler)
        render_status(spieler, GOAL_POS, ANALYSE)
        print('Ziel erreicht.')
        break