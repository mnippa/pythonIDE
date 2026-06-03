# Labyrinth 15x15 - Rechte-Hand-Regel

Im selben Grundmuster wie Schritt 3:
- Steuerung mit `l`, `r`, `Enter`, `q`
- Richtungsvektor und Pfeilsymbol als Spieleranzeige
- Konsolen-Render in jeder Runde

Das Spielfeld steht fest als 2D-Array in `spielfeld.py` und hat folgende Eigenschaften:
- etwa 20-25 Schritte bis zum Ziel
- einmal knapp am Ziel vorbei
- geradeaus, links und umkehren kommen mindestens einmal vor

Regel fuer die Navigation:
1. Wenn rechts frei ist: rechts drehen und gehen
2. Sonst wenn vorne frei: geradeaus gehen
3. Sonst wenn links frei: links drehen und gehen
4. Sonst: umkehren