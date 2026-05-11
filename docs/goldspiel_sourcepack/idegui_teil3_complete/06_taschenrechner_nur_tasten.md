# IDEGUI Teil 3 - 06_taschenrechner_nur_tasten

Projekt: 48 (IDEGUI Teil 3 - Taschenrechner systematisch)

## Inhalte

### README.md

```markdown
# 06 Demo - Taschenrechner nur mit Tasten

Ziel:
- Komplett ohne Texteingabe arbeiten.
- Alle Interaktionen laufen ueber Buttons mit `data-function="taste"`.

Didaktik:
- `eingabe` speichert die aktuell getippte Zahl als Text.
- `links` und `operator` halten den Zwischenzustand.
- Tasten:
  - `0-9`: Zahl aufbauen
  - `+ - * /`: Operator setzen
  - `+/-`: Vorzeichen der aktuellen Eingabe (oder des Ergebnisses) wechseln
  - `=`: Berechnen
  - `C`: Zuruecksetzen

Hinweis:
- Division durch 0 wird abgefangen und als Meldung angezeigt.
```

### index.html

```html
<!doctype html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Demo - Taschenrechner nur mit Tasten</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="calc-app">
    <section class="calc-shell">
      <h1>Demo 06</h1>
      <p class="subtitle">Nachbau: Taschenrechner nur mit Tasten</p>

      <div class="display-wrap">
        <div class="expr" data-element="ausdruck">0</div>
        <div class="result" data-element="anzeige">0</div>
      </div>

      <div class="keys">
        <button class="k fn" data-function="taste" value="C">C</button>
        <button class="k fn" data-function="taste" value="sign">+/-</button>
        <button class="k op" data-function="taste" value="/">/</button>
        <button class="k op" data-function="taste" value="*">*</button>

        <button class="k num" data-function="taste" value="7">7</button>
        <button class="k num" data-function="taste" value="8">8</button>
        <button class="k num" data-function="taste" value="9">9</button>
        <button class="k op" data-function="taste" value="-">-</button>

        <button class="k num" data-function="taste" value="4">4</button>
        <button class="k num" data-function="taste" value="5">5</button>
        <button class="k num" data-function="taste" value="6">6</button>
        <button class="k op" data-function="taste" value="+">+</button>

        <button class="k num" data-function="taste" value="1">1</button>
        <button class="k num" data-function="taste" value="2">2</button>
        <button class="k num" data-function="taste" value="3">3</button>
        <button class="k eq tall" data-function="taste" value="=">=</button>

        <button class="k num wide" data-function="taste" value="0">0</button>
      </div>

      <div class="hint" data-element="meldung">Bereit.</div>
    </section>
  </main>
</body>
</html>
```

### init.py

```python
import idegui as ui

eingabe = ''
links = None
operator = None


def format_num(value):
    if int(value) == value:
        return str(int(value))
    return str(value)


def parse_num_text(text):
    text = str(text).strip()
    if text in ['', '-']:
        return 0.0
    return float(text)


def rechne(a, b, op):
    if op == '+':
        return a + b
    if op == '-':
        return a - b
    if op == '*':
        return a * b
    if op == '/':
        if b == 0:
            raise ValueError('Division durch 0 ist nicht erlaubt.')
        return a / b
    raise ValueError('Unbekannter Operator: ' + str(op))


def refresh_view(message=''):
    global eingabe, links, operator

    if operator is None:
        if eingabe == '':
            expr = '0'
        else:
            expr = eingabe
    else:
        left_text = format_num(float(links)) if links is not None else '0'
        right_text = eingabe if eingabe != '' else ''
        expr = left_text + ' ' + operator + ' ' + right_text

    if eingabe not in ['', '-']:
        anzeige = eingabe
    elif links is not None:
        anzeige = format_num(float(links))
    else:
        anzeige = '0'

    ui.set('ausdruck', expr)
    ui.set('anzeige', anzeige)
    ui.set('meldung', message if message else 'Bereit.')


def set_operator(op):
    global eingabe, links, operator

    if eingabe not in ['', '-']:
        value = parse_num_text(eingabe)
        if links is None:
            links = value
        elif operator is not None:
            links = rechne(float(links), float(value), operator)
        eingabe = ''

    if links is None:
        links = 0.0

    operator = op
    refresh_view('Operator gesetzt: ' + op)


def toggle_sign():
    global eingabe, links, operator

    if eingabe != '':
        if eingabe.startswith('-'):
            eingabe = eingabe[1:]
            if eingabe == '':
                eingabe = '0'
        else:
            eingabe = '-' + eingabe
        refresh_view('Vorzeichen gewechselt.')
        return

    if links is not None:
        links = -float(links)
        refresh_view('Vorzeichen vom Ergebnis gewechselt.')
        return

    eingabe = '-'
    refresh_view('Negatives Vorzeichen aktiv.')


def calc_equals():
    global eingabe, links, operator

    if operator is None:
        refresh_view('Kein Operator gesetzt.')
        return

    rechts = parse_num_text(eingabe)
    links_wert = float(links) if links is not None else 0.0
    result = rechne(links_wert, float(rechts), operator)

    links = result
    eingabe = ''
    operator = None
    refresh_view('Ergebnis berechnet.')


def taste(trigger):
    global eingabe, links, operator

    value = str(getattr(trigger, 'value', '') or '').strip()

    if value == 'C':
        eingabe = ''
        links = None
        operator = None
        refresh_view('Zurueckgesetzt.')
        return

    if value == 'sign':
        toggle_sign()
        return

    if value in ['+', '-', '*', '/']:
        try:
            set_operator(value)
        except Exception as ex:
            refresh_view('Fehler: ' + str(ex))
        return

    if value == '=':
        try:
            calc_equals()
        except Exception as ex:
            refresh_view('Fehler: ' + str(ex))
        return

    if value.isdigit():
        if eingabe in ['0', '-0']:
            eingabe = ('-' if eingabe.startswith('-') else '') + value
        elif eingabe == '-':
            eingabe = '-' + value
        else:
            eingabe = eingabe + value
        refresh_view('Zahl erweitert.')
        return

    refresh_view('Unbekannte Taste: ' + value)
```

### style.css

```css
.calc-app {
  --bg1: #0f172a;
  --bg2: #1e293b;
  --shell: #0b1220;
  --screen: #020617;
  --num: #1f2937;
  --op: #0ea5e9;
  --eq: #22c55e;
  --fn: #ef4444;
  --txt: #e2e8f0;

  min-height: 100%;
  border-radius: 12px;
  padding: 20px;
  background: radial-gradient(circle at 20% 10%, #334155 0%, var(--bg1) 55%, #020617 100%);
  font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
  color: var(--txt);
}

.calc-shell {
  max-width: 380px;
  margin: 0 auto;
  border-radius: 18px;
  padding: 18px;
  background: linear-gradient(165deg, var(--bg2) 0%, var(--shell) 100%);
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45);
}

.calc-shell h1 {
  margin: 0;
  font-size: 22px;
  text-align: center;
}

.calc-shell .subtitle {
  margin: 6px 0 14px 0;
  font-size: 12px;
  text-align: center;
  color: #94a3b8;
}

.display-wrap {
  background: var(--screen);
  border-radius: 12px;
  padding: 10px 12px;
  margin-bottom: 12px;
  border: 1px solid #1e293b;
}

.expr {
  min-height: 22px;
  font-size: 13px;
  color: #94a3b8;
  text-align: right;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.result {
  min-height: 40px;
  font-size: 30px;
  font-weight: 700;
  line-height: 1.2;
  text-align: right;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.keys {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}

.k {
  border: none;
  border-radius: 10px;
  min-height: 48px;
  font-size: 18px;
  font-weight: 700;
  color: #fff;
  cursor: pointer;
  transition: transform 0.08s ease;
}

.k:active {
  transform: scale(0.96);
}

.k.num { background: var(--num); }
.k.op { background: var(--op); }
.k.eq { background: var(--eq); }
.k.fn { background: var(--fn); }

.k.wide {
  grid-column: span 3;
}

.k.tall {
  grid-row: span 2;
}

.hint {
  margin-top: 10px;
  min-height: 24px;
  font-size: 12px;
  color: #94a3b8;
  text-align: center;
}
```

