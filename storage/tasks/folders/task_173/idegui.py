import idegui as ui

ui.title('Dynamische UI: Einfaches Beispiel')

zahl_a = ui.number('Zahl A', 10)
zahl_b = ui.number('Zahl B', 20)
ausgabe = ui.output()

def addiere():
    ausgabe.clear()
    try:
        gesamt = float(zahl_a.value) + float(zahl_b.value)
        ausgabe.write(f'Summe: {gesamt}')
    except Exception:
        ausgabe.write('Fehler: Eingaben sind ungültig')

ui.button('Addieren').on_click(addiere)
ausgabe.write('Klicke auf "Addieren".')