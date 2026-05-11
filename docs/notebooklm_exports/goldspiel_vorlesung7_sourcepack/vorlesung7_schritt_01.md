# Projekt 45 - Schritt 01 (01_spielfeld)

## Inhalte

### README.md

```markdown
# 01 Spielfeld

In diesem ersten Schritt wird nur das Spielfeld dargestellt.
Es gibt noch keinen Spieler und noch keine Bewegung.

## spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]
```

## function.py

```python
SYMBOLE = {
    0: '  ',
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
```

## main.py

```python
from spielfeld import SPIELFELD
from function import render

render(SPIELFELD)
```

## Kurze Zusammenfassung

- `spielfeld.py` enthaelt das 9x9-Spielfeld als Liste von Listen.
- Die Zahlen im Feld stehen fuer leer, Wand, Start, Gold und Ziel.
- `function.py` enthaelt bisher nur den Renderer.
- `main.py` importiert das Spielfeld und ruft den Renderer auf.
- Dieser Schritt eignet sich gut, um zuerst Datenmodell und Ausgabe zu verstehen.
```

### function.py

```python
SYMBOLE = {
    0: '  ',
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
```

### main.py

```python
from spielfeld import SPIELFELD
from function import render

render(SPIELFELD)
```

### spielfeld.py

```python
# 0 = leer
# 1 = Wand
# 2 = Start
# 7 = Gold
# 9 = Ziel

SPIELFELD = [
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
    [1, 2, 0, 0, 0, 0, 0, 9, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 0, 0, 0, 0, 0, 0, 0, 1],
    [1, 1, 1, 1, 1, 1, 1, 1, 1],
]
```
