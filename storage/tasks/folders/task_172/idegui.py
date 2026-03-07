import idegui as ui

# Trigger-Werte aus der Runtime übernehmen
ui._refresh_trigger()

try:
    a = float(ui.get('a', '0'))
    b = float(ui.get('b', '0'))
except ValueError:
    ui.set('result', 'Fehler: Bitte gültige Zahlen eingeben')
else:
    if ui.trigger.name == 'plus':
        ui.set('result', f"{a} + {b} = {a + b}")
    elif ui.trigger.name == 'minus':
        ui.set('result', f"{a} - {b} = {a - b}")
    elif ui.trigger.name == 'mal':
        ui.set('result', f"{a} * {b} = {a * b}")
    elif ui.trigger.name == 'geteilt':
        if b == 0:
            ui.set('result', 'Fehler: Division durch 0')
        else:
            ui.set('result', f"{a} / {b} = {a / b}")
    else:
        ui.set('result', 'Bitte eine Operation klicken')