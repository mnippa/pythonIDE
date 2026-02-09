# VARIABLE-Testing: Workflow für Studenten

## Problem

Bei VARIABLE-Tests setzen Studenten KEINE eigenen Werte - das macht das System!

**ABER:** Wie testen Studenten dann ihren Code während der Entwicklung?

---

## Lösung: 2-Button-System

### **▶ RUN** - Freies Testen
- Student fügt temporär Testwerte ein
- Klickt RUN
- Sieht Ergebnis
- Debuggt Code

### **✓ CHECK** - Offizielle Prüfung
- Student **entfernt alle Wertzuweisungen**
- Nur Berechnungen bleiben
- Klickt CHECK
- System setzt automatisch verschiedene Werte

---

## Workflow für Studenten

### Beispiel: Summe und Produkt berechnen

**Schritt 1: Entwickeln**
```python
# Temporäre Testwerte (für RUN)
a = 8
b = 12

# Lösung
summe = a + b
produkt = a * b
```
→ Klick **▶ RUN** → Sieht `summe = 20`, `produkt = 96` ✓

**Schritt 2: Debuggen**
```python
# Test mit anderen Werten
a = 5
b = 10

summe = a + b
produkt = a * b

print(f"Summe: {summe}, Produkt: {produkt}")
```
→ Klick **▶ RUN** → Prüft Ausgabe

**Schritt 3: Abgeben**
```python
# ALLE Wertzuweisungen entfernt!
summe = a + b
produkt = a * b
```
→ Klick **✓ CHECK** → System testet mit verschiedenen Werten

---

## Warum MÜSSEN Wertzuweisungen entfernt werden?

### Technischer Hintergrund

```python
# System macht intern:
namespace = {}
namespace.update({'a': 5, 'b': 10})  # Test-Werte setzen
exec(student_code, namespace)         # Code ausführen
```

**Wenn Student `a = 7` im Code hat:**
```python
namespace = {'a': 5, 'b': 10}  # System setzt
exec("a = 7\nsumme = a + b")   # Student überschreibt a!
# → namespace = {'a': 7, 'b': 10, 'summe': 17}
# Erwartet: summe = 15
# Test schlägt fehl! ❌
```

**Ohne Wertzuweisungen:**
```python
namespace = {'a': 5, 'b': 10}  # System setzt
exec("summe = a + b")          # Student nutzt Werte
# → namespace = {'a': 5, 'b': 10, 'summe': 15}
# Test besteht! ✓
```

---

## Code-Template Struktur

**Gutes Template:**
```python
# Berechne Summe und Produkt von a und b
# Für manuelles Testen: Füge temporär "a = 8" und "b = 12" ein
# Für CHECK: Entferne alle a = ... und b = ... Zeilen!
summe = a ___ b
produkt = a ___ b
```

**Hinweise im Template:**
- ✅ Erklärt was init_vars sind
- ✅ Anleitung für manuelles Testen
- ✅ Warnung vor Wertzuweisungen
- ✅ Nur Berechnungs-Code

---

## Task-Description Best Practices

**Muss enthalten:**

1. **Test-Typ klar benennen:** `**TEST-TYP: VARIABLE**`

2. **Workflow erklären:**
   ```
   **So arbeiten Sie:**
   
   1. **Entwickeln & Testen:**
      - Fügen Sie temporär `a = 8` und `b = 12` ein
      - Klicken Sie ▶ RUN zum Testen
   
   2. **Abgeben:**  
      - WICHTIG: Entfernen Sie alle `a = ...` Zeilen!
      - Klicken Sie ✓ CHECK
   ```

3. **Warnung vor Überschreiben:**
   ```
   **Warum?** Wertzuweisungen überschreiben die Auto-Test-Werte!
   ```

---

## Alternative: Kommentierte Testwerte

**Problem:** Studenten vergessen Zeilen zu entfernen.

**Lösung:** Testwerte auskommentiert im Template
```python
# === ZUM TESTEN ===
# Entfernen Sie die # um mit RUN zu testen
# a = 7
# b = 12
# ==================

# === LÖSUNG ===
# Für CHECK bleiben die Werte auskommentiert!
summe = a + b
produkt = a * b
```

**Vorteil:** Student kann nicht vergessen zu entfernen
**Nachteil:** Muss # entfernen und wieder hinzufügen

---

## UI-Verbesserung: Info-Box

**Idee:** Info-Hinweis beim Öffnen einer VARIABLE-Task

```
ℹ️ TEST-TYP: VARIABLE

Zum Testen: Fügen Sie temporär Werte ein (z.B. a = 5)
Zum Abgeben: Entfernen Sie ALLE Wertzuweisungen!

Grund: Das System setzt die Werte automatisch.
```

---

## Zusammenfassung

| Phase | Code-Inhalt | Button | Verhalten |
|-------|-------------|--------|-----------|
| **Entwickeln** | `a = 8\nb = 12\nsumme = a + b` | ▶ RUN | Läuft mit a=8, b=12 |
| **Testen** | `a = 5\nb = 10\nsumme = a + b` | ▶ RUN | Läuft mit a=5, b=10 |
| **Abgeben** | `summe = a + b` | ✓ CHECK | System setzt verschiedene Werte |

**Kritisch:** Bei CHECK dürfen KEINE Wertzuweisungen für init_vars im Code sein!

---

## Häufige Fehler

### ❌ Fehler 1: Testwerte vergessen zu entfernen
```python
a = 7  # FALSCH! Überschreibt Test-Werte!
summe = a + b
```
**Lösung:** Template mit Warnung, klare Anleitung

### ❌ Fehler 2: Denkt Kommentare werden ignoriert
```python
# a = 7  # Student denkt das wird bei CHECK automatisch ignoriert
summe = a + b
```
**Korrekt:** Auskommentierte Zeilen sind kein Problem! Die führen nicht zu Wertzuweisungen.

### ❌ Fehler 3: Verwechslung mit FUNCTION-Tests
Student denkt es ist wie FUNCTION-Testing wo Args explizit sind.
**Lösung:** Test-Typ klar kennzeichnen

---

## Siehe auch

- `scripts/create_test_type_examples.php` - Beispiel-Templates
- `scripts/update_variable_templates.php` - Update bestehender Tasks  
- `docs/test-types.md` - Vollständige Dokumentation
