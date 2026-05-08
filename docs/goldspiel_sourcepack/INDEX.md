# Goldspiel Source Pack

Enthaelt vollstaendige Quellen fuer:
- Projekt 45: Vorlesung 7 (Schritte 01-07)
- Projekt 46: IDEGUI Demo (Event Driven)

Exportdatum: 2026-05-07 19:01:19

- `vorlesung7_schritt_01.md`
- `vorlesung7_schritt_02.md`
- `vorlesung7_schritt_03.md`
- `vorlesung7_schritt_04.md`
- `vorlesung7_schritt_05.md`
- `vorlesung7_schritt_06.md`
- `vorlesung7_schritt_07.md`
- `idegui_teil3_taschenrechner.md`

## Projekt 45 Root-Dateien

### init.py

```python
print('Vorlesung 7 v2 geladen. Oeffne einen Unterordner und starte dort main.py oder init.py.')
```

### README.md

```markdown
# Vorlesung 7 - Spiel, Labyrinth, UI (v2 ohne Tupel)

Dieses Projekt ist fuer Live-Demos aufgebaut.

Leitprinzipien:
- Keine Tupel
- Positionen als Listen: [x, y]
- Spielerzustand als Dict
- Feldzustaende: 1 Wand, 0 Leer, 2 Start, 9 Ziel, 7 Gold
- Spieler wird separat gespeichert

Ordnerreihenfolge:
1. 01_spielfeld
2. 02_spieler_repraesentation_und_visualisierung
3. 03_bewegung_und_kollision
4. 04_gold_und_ziel
5. 05_zufaellige_platzierung
6. 06_spielfelder_laden
7. 07_kritischer_pfad

Die frueheren Taschenrechner-Demos wurden in ein separates Projekt ausgelagert.
```

- `idegui_demo_goldspiel.md`