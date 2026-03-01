"""
MwSt-Rechner - Musterlösung
Assignment #21 - Task: MwSt-Rechner (19%)

Diese Datei kann direkt ausgeführt werden zum Testen.
"""

print("=" * 50)
print("🧮 MwSt-Rechner (19%)")
print("=" * 50)
print()

# Nettopreis vom Benutzer einlesen
netto = input("Nettopreis in Euro: ")

# In Zahl konvertieren
netto = float(netto)

# Bruttopreis berechnen (19% MwSt)
brutto = netto * 1.19

# MwSt-Betrag berechnen
mwst_betrag = netto * 0.19

# Ergebnis ausgeben
print()
print("-" * 50)
print(f"Nettopreis:  {netto:>10.2f} €")
print(f"MwSt (19%):  {mwst_betrag:>10.2f} €")
print("-" * 50)
print(f"Bruttopreis: {brutto:>10.2f} €")
print("=" * 50)
