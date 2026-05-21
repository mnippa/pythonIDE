SYMBOLE = {
    0: '🟩',
    1: '⬛',
    2: '🟦',
    7: '🪙',
    9: '🚪',
}


def render(spielfeld):
    for zeile in spielfeld:
        ausgabe = ''
        for wert in zeile:
            ausgabe = ausgabe + SYMBOLE[wert]
        print(ausgabe)