import idegui as ui

if 'active_count' not in globals():
    active_count = 3


def render_app(count):
    global active_count
    active_count = max(1, min(5, int(count)))

    ui.title('Dynamische Summen-App (Elemente ersetzen)')

    count_select = ui.select('Wie viele Zahlen? (1-5)', options=[1, 2, 3, 4, 5], value=active_count)
    btn_rebuild = ui.button('Eingabefelder ersetzen')
    btn_calc = ui.button('Summe berechnen')

    number_widgets = []
    for index in range(active_count):
        number_widgets.append(ui.number(f'Zahl {index + 1}', 0))

    status = ui.output()
    status.write(f'{active_count} Eingabefelder aktiv.')

    def rebuild_ui():
        try:
            next_count = int(count_select.value)
        except Exception:
            next_count = active_count
        render_app(next_count)

    def calc_sum():
        total = 0.0
        for widget in number_widgets:
            total += float(widget.value)
        status.clear()
        status.write(f'Summe aus {len(number_widgets)} Feldern: {total}')

    btn_rebuild.on_click(rebuild_ui)
    btn_calc.on_click(calc_sum)


render_app(active_count)