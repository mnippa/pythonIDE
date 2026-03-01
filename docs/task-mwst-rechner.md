# MwSt-Rechner - Beispielaufgabe mit input()

> **Assignment**: #21 | **Task Type**: Code mit Input | **Schwierigkeit**: Leicht

---

## 📋 Aufgabenstellung

Schreibe ein Python-Programm, das einen **Nettopreis** vom Benutzer einliest und den **Bruttopreis** (inkl. 19% MwSt) berechnet und ausgibt.

---

## ✅ Anforderungen

### 1. Input
- Verwende `input()` um nach dem Nettopreis zu fragen
- Konvertiere die Eingabe mit `float()` oder `int()` in eine Zahl

### 2. Berechnung
- Berechne den Bruttopreis: `Nettopreis × 1.19`
- Alternative: `Nettopreis + (Nettopreis × 0.19)`

### 3. Output
- Gib den Bruttopreis mit `print()` aus
- Formatierung mit 2 Nachkommastellen empfohlen: `:.2f`

---

## 🧪 Test-Cases

Das System prüft automatisch:

| Test | Beschreibung | Pattern |
|------|--------------|---------|
| ✓ Input | `input()` wird verwendet | `input\s*\(` |
| ✓ Konvertierung | `float()` oder `int()` wird verwendet | `(float\|int)\s*\(` |
| ✓ Berechnung | Multiplikation mit 1.19 oder Addition von 0.19 | `*\s*1\.19\|+.*0\.19` |
| ✓ Output | `print()` gibt Ergebnis aus | `print\s*\(` |

---

## 💡 Beispiel-Ausführungen

### Beispiel 1: 100 Euro
```
Nettopreis in Euro: 100
Bruttopreis: 119.00 Euro
```

### Beispiel 2: 49.99 Euro
```
Nettopreis in Euro: 49.99
Bruttopreis: 59.49 Euro
```

### Beispiel 3: 250 Euro mit MwSt-Anzeige
```
Nettopreis in Euro: 250
Bruttopreis: 297.50 Euro
(enthält 47.50 Euro MwSt)
```

---

## 🎯 Musterlösung

```python
# Musterlösung: MwSt-Rechner

# Nettopreis vom Benutzer einlesen
netto = input("Nettopreis in Euro: ")

# In Zahl konvertieren
netto = float(netto)

# Bruttopreis berechnen (19% MwSt)
brutto = netto * 1.19

# Ergebnis ausgeben
print(f"Bruttopreis: {brutto:.2f} Euro")
print(f"(enthält {netto * 0.19:.2f} Euro MwSt)")
```

### Kompakte Variante:
```python
netto = float(input("Nettopreis: "))
print(f"Bruttopreis: {netto * 1.19:.2f} Euro")
```

### Alternative Berechnung:
```python
netto = float(input("Nettopreis: "))
mwst = netto * 0.19
brutto = netto + mwst
print(f"Brutto: {brutto:.2f} € (Netto: {netto:.2f} € + MwSt: {mwst:.2f} €)")
```

---

## 📚 Hintergrundwissen

### MwSt in Deutschland
- **Regelsteuersatz**: 19%
- **Ermäßigter Satz**: 7% (Lebensmittel, Bücher, etc.)
- **Berechnung**: Nettopreis × 1,19 = Bruttopreis

### Formeln

```
Bruttopreis = Nettopreis × (1 + MwSt-Satz)
Bruttopreis = Nettopreis × 1,19

MwSt-Betrag = Nettopreis × 0,19
Bruttopreis = Nettopreis + MwSt-Betrag
```

### Gegenteil: Netto aus Brutto berechnen
```
Nettopreis = Bruttopreis / 1,19
```

---

## 🚀 Installation in Datenbank

```bash
# MySQL/MariaDB
mysql -u root -p pythonide < sql/add_mwst_task.sql

# Oder über phpMyAdmin:
# 1. Öffne phpMyAdmin
# 2. Wähle Datenbank "pythonide"
# 3. Gehe zu "SQL" Tab
# 4. Kopiere Inhalt von add_mwst_task.sql
# 5. Führe aus
```

---

## 🎓 Lernziele

Nach dieser Aufgabe können Studierende:

- ✅ `input()` für Benutzereingaben verwenden
- ✅ String-zu-Float-Konvertierung mit `float()`
- ✅ Mathematische Berechnungen durchführen
- ✅ Formatierte Ausgabe mit f-Strings und `:.2f`
- ✅ Prozentrechnung in der Praxis anwenden

---

## 🔧 Erweiterte Varianten

### Variante A: Mit Fehlerbehandlung
```python
try:
    netto = float(input("Nettopreis: "))
    brutto = netto * 1.19
    print(f"Bruttopreis: {brutto:.2f} Euro")
except ValueError:
    print("Fehler: Bitte eine Zahl eingeben!")
```

### Variante B: Mit Auswahl des MwSt-Satzes
```python
netto = float(input("Nettopreis: "))
satz = input("MwSt-Satz (19 oder 7): ")

if satz == "19":
    brutto = netto * 1.19
elif satz == "7":
    brutto = netto * 1.07
else:
    print("Ungültiger MwSt-Satz!")
    exit()

print(f"Bruttopreis: {brutto:.2f} Euro")
```

### Variante C: Als Funktion
```python
def berechne_brutto(netto, mwst_satz=19):
    """Berechnet Bruttopreis aus Nettopreis"""
    faktor = 1 + (mwst_satz / 100)
    return netto * faktor

netto = float(input("Nettopreis: "))
brutto = berechne_brutto(netto)
print(f"Bruttopreis: {brutto:.2f} Euro")
```

---

## 📖 Verwandte Aufgaben

- **Aufgabe 1**: Temperatur-Umrechner (Celsius → Fahrenheit)
- **Aufgabe 2**: Altersberechnung aus Geburtsjahr
- **Aufgabe 3**: BMI-Rechner (Gewicht + Größe)
- **Aufgabe 4**: Taschenrechner (2 Zahlen + Operator)

---

**Erstellt**: März 2026 | **Phase**: 2.1/2.2 | **Status**: Production Ready ✅
