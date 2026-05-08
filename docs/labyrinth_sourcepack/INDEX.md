# Labyrinth Source Pack (Projekt 47)

Dieses Paket enthaelt pro Schritt:
- die README
- den vollstaendigen Python-Quellcode
- die kompletten Labyrinth-Leveldateien

Exportdatum: 2026-05-07 18:58:38

- `schritt_01.md`: Schritt 01 - Ausgangssituation (manuell)
- `schritt_02.md`: Schritt 02 - Rechtehandregel
- `schritt_03.md`: Schritt 03 - BFS (kuerzester Pfad)
- `schritt_04.md`: Schritt 04 - Demo Rechtehand vs Tremaux

## Root-Datei

```python
import os
import runpy

BASE_DIR = os.path.dirname(__file__)

# Projektstart: Schritt auswaehlen
# 01 = manuelle Ausgangssituation
# 02 = Implementierung mit Rechtehandregel
# 03 = BFS (kuerzester sicherer Pfad)
# 04 = Live-Demo Rechtehandregel vs Tremaux
schritt = input('Schritt waehlen (01/02/03/04), Enter fuer 01: ').strip()
if schritt == '02':
    step_folder = '02_Rechtehandregel'
elif schritt == '03':
    step_folder = '03_BFS_Kuerzester_Pfad'
elif schritt == '04':
    step_folder = '04_Demo_Rechtehand_vs_Tremaux'
else:
    step_folder = '01_Labyrint_manuell'

MAIN_PATH = os.path.join(BASE_DIR, step_folder, 'main.py')
runpy.run_path(MAIN_PATH, run_name='__main__')
```