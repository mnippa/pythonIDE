import idegui as ui

# Aktualisiere den Trigger mit den neuesten Werten vom JavaScript
ui._refresh_trigger()

# Lese die beiden Eingabewerte
try:
    a = float(ui.get('a', '0'))
except ValueError:
    a = 0

try:
    b = float(ui.get('b', '0'))
except ValueError:
    b = 0

# Führe die entsprechende Operation basierend auf dem Trigger aus
if ui.trigger.name == "plus":
    result = a + b
    ui.set('result', f"{a} + {b} = {result}")

elif ui.trigger.name == "minus":
    result = a - b
    ui.set('result', f"{a} − {b} = {result}")

elif ui.trigger.name == "mal":
    result = a * b
    ui.set('result', f"{a} × {b} = {result}")

elif ui.trigger.name == "geteilt":
    if b != 0:
        result = a / b
        ui.set('result', f"{a} ÷ {b} = {result}")
    else:
        ui.set('result', "Fehler: Division durch Null!")
