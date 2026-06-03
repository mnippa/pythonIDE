from spielfeld import SPIELFELD, finde_startposition, count_initial_gold
from function import render, render_status, drehe_links, drehe_rechts, gehe_vorwaerts, ist_ziel

start = finde_startposition(SPIELFELD)

spieler = {
    'posX': start['x'],
    'posY': start['y'],
    'vX': 1,
    'vY': 0,
    'steps': 0,
    'turns': 0,
    'gold': 0,
}

target_gold = count_initial_gold(SPIELFELD)

while True:
    outputClear()
    render(SPIELFELD, spieler)
    render_status(spieler, target_gold)
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
        render_status(spieler, target_gold)
        if spieler['gold'] >= target_gold:
            print('Ziel erreicht und alle Muenzen gesammelt.')
            break
        print('Ziel erreicht, aber noch nicht alle Muenzen gesammelt.')