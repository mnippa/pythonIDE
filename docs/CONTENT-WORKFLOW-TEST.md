# Inhaltlicher Workflow-Testplan – pythonIDE
## Didaktische User Journeys & Lernszenarien

Stand: 09.03.2026

---

## Testphilosophie

**Nicht:** "Lädt die Seite?" (das prüfen technische Tests)  
**Sondern:** "Kann ein Student damit Python lernen?" und "Kann ein Dozent damit unterrichten?"

---

## Vorbereitung

### Test-Accounts
- **Student (Anfänger):** student@test.local / Password123
- **Student (Fortgeschritten):** advanced@test.local / Password123  
- **Dozent/Admin:** admin / admin123

### Test-Assignments
Prüfe vorher, dass diese Assignments existieren und zugewiesen sind:
- Assignment mit **reinen Code-Tasks** (z.B. "Python Basics")
- Assignment mit **Quiz-Tasks** (z.B. "Aussagenlogik Quiz")
- Assignment mit **Projekt-Tasks** (idegui HTML/Python)
- Assignment mit **Intelligent Tests** (Code mit Randomisierung)
- Assignment mit **Check-Limits** (max_attempts gesetzt)

---

# TESTPHASEN: LOGISCHE REIHENFOLGE

**Wichtig:** Zuerst Admin-Setup testen, dann Student-Nutzung!

## Phase 1: DOZENTEN-SETUP (45 Min)
→ Aufgaben erstellen, Assignments anlegen, zuweisen  
→ **Ohne funktionierende Admin-Workflows können Studenten nichts testen!**
→ Copy/Paste-Vorlagen für GUI-Anlage: [PHASE1-COPY-PASTE.md](PHASE1-COPY-PASTE.md)
→ Ultra-kurze Variante: [PHASE1-COPY-PASTE-SHORT.md](PHASE1-COPY-PASTE-SHORT.md)

## Phase 2: STUDENT-NUTZUNG (60 Min)
→ Mit den vom Admin erstellten Inhalten arbeiten  
→ Lernen, Aufgaben lösen, Fortschritt speichern

## Phase 3: ROBUSTHEIT (30 Min, optional)
→ Edge Cases und Stress-Tests

---

# PHASE 1: DOZENTEN-WORKFLOWS (Admin geht zuerst!)

## D1 – Aufgaben erstellen & verwalten

### D1.1 Neue Code-Aufgabe erstellen (START HIER!)
- [ ] Logge dich als Admin ein (admin / admin123)
- [ ] Öffne Admin-Panel
- [ ] Klicke "Neue Aufgabe"
- [ ] **Prüfung:** Pre-Task-Dialog erscheint?
- [ ] Wähle Type: "Code"
- [ ] Gib Title: "Test: Addition"
- [ ] Klicke "Weiter"
- [ ] **Prüfung:** Type/Title jetzt disabled?
- [ ] Fülle aus:
  - Beschreibung: "Addiere x und y und speichere in result"
  - Code Template: `x = 5\ny = 3\n# Schreibe Code hier`
  - Expected Output: `8`
- [ ] Klicke "Aufgabe speichern"
- [ ] **Prüfung:** Success-Meldung?
- [ ] Lade Seite neu
- [ ] **Prüfung:** Aufgabe in Liste sichtbar?

**Didaktischer Check:** ✅ Aufgabenerstellung intuitiv, ohne Datenverlust

---

### D1.2 Code-Aufgabe mit Hints
- [ ] Erstelle neue Aufgabe
- [ ] Type: "Code"
- [ ] Title: "Test: Multiplikation"
- [ ] Beschreibung: "Multipliziere a und b"
- [ ] Code Template: `a = 4\nb = 7\n# Dein Code`
- [ ] Expected Output: `28`
- [ ] **WICHTIG:** Setze Hints:
  - Hint 1: "Nutze den * Operator"
  - Hint 2: "Die Formel lautet: a * b"
  - Hint 3: "Speichere das Ergebnis in: result = a * b"
- [ ] Setze: Max Attempts = 3
- [ ] Aktiviere: Show Solution = Ja
- [ ] Speichern
- [ ] **Prüfung:** Hints im Admin sichtbar?

**Didaktischer Check:** ✅ Hints-System konfigurierbar

---

### D1.3 Projekt mit Folder-Struktur erstellen
- [ ] Erstelle neue Aufgabe
- [ ] Type: "Project (Folder Structure)"
- [ ] Title: "Test: Mini-Taschenrechner"
- [ ] Beschreibung: "Erstelle einen einfachen Taschenrechner mit HTML und Python"
- [ ] Aktiviere: "Folder Structure"
- [ ] Definiere Files:
  ```
  init.py (mit idegui Event-Handler)
  index.html (mit Button + Input)
  style.css (für Basic-Styling)
  ```
- [ ] Fülle init.py Template:
  ```python
  import idegui as ui

  def addieren(trigger):
      zahl1 = float(ui.get('input1'))
      zahl2 = float(ui.get('input2'))
      ergebnis = zahl1 + zahl2
      ui.set('output', str(ergebnis))

  ui.set('output', '0')
  ```
- [ ] Fülle index.html Template:
  ```html
  <!DOCTYPE html>
  <html>
  <body>
    <input data-element="input1" type="number">
    <input data-element="input2" type="number">
    <button data-function="addieren">Addieren</button>
    <div data-element="output">0</div>
  </body>
  </html>
  ```
- [ ] Speichern
- [ ] **Prüfung:** Im Admin alle 3 Files sichtbar?
- [ ] **Prüfung:** Templates editierbar?

**Didaktischer Check:** ✅ Multi-File-Setup transparent konfiguriert

---

### D1.4 Intelligent Test mit Randomisierung
- [ ] Erstelle neue Aufgabe
- [ ] Type: "Code"
- [ ] Title: "Test: Intelligent Multiply"
- [ ] Beschreibung: "Multipliziere die gegebenen Zahlen x und y"
- [ ] **Aktiviere:** Intelligent Test
- [ ] **Solution Code:**
  ```python
  result = x * y
  ```
- [ ] **Randomizer Code:**
  ```python
  import random
  x = random.randint(1, 10)
  y = random.randint(1, 10)
  ```
- [ ] Speichern
- [ ] **Prüfung:** Beide Code-Felder gespeichert?
- [ ] **Prüfung:** "Intelligent Test" Badge sichtbar?

**Didaktischer Check:** ✅ Randomisierung konfigurierbar

---

### D1.5 Quiz erstellen
- [ ] Erstelle neue Aufgabe
- [ ] Type: "Quiz"
- [ ] Title: "Test: Python Basics Quiz"
- [ ] Question Text: "Was gibt print(2+2) aus?"
- [ ] Fülle Antworten:
  - Antwort A: "4" → **Markiere als korrekt**
  - Antwort B: "22"
  - Antwort C: "Fehler"
  - Antwort D: "2+2"
- [ ] Speichern
- [ ] **Prüfung:** Quiz im Admin sichtbar?
- [ ] **Prüfung:** Richtige Antwort grün markiert?

**Didaktischer Check:** ✅ Quiz-Erstellung funktioniert

---

## D2 – Assignments verwalten

### D2.1 Assignment erstellen und Tasks hinzufügen
- [ ] Gehe zu "Assignments" im Admin
- [ ] Klicke "Neues Assignment"
- [ ] Name: "Test-Assignment März 2026"
- [ ] Beschreibung: "Testaufgaben für Beta-Validierung"
- [ ] Speichern
- [ ] **Prüfung:** Assignment in Liste?
- [ ] Öffne das Assignment
- [ ] Füge Tasks hinzu (die gerade erstellten):
  1. "Test: Addition" (Code)
  2. "Test: Multiplikation" (Code mit Hints)
  3. "Test: Mini-Taschenrechner" (Projekt)
  4. "Test: Intelligent Multiply" (Randomisiert)
  5. "Test: Python Basics Quiz" (Quiz)
- [ ] **Prüfung:** Alle 5 Tasks im Assignment?
- [ ] **Prüfung:** Reihenfolge änderbar? (Position-Felder oder Drag&Drop)

**Didaktischer Check:** ✅ Assignment-Zusammenstellung funktioniert

---

### D2.2 Assignment einem Student zuweisen
- [ ] Wähle Assignment "Test-Assignment März 2026"
- [ ] Klicke "Studenten zuweisen" (oder ähnlich)
- [ ] Wähle Test-Student aus Liste
  - Falls kein Student existiert: Erstelle einen (student@test.local / Password123)
- [ ] Aktiviere Assignment (is_active = true)
- [ ] Speichern
- [ ] **Prüfung:** Success-Meldung?
- [ ] **Prüfung:** Zuweisung in Liste sichtbar?

**Didaktischer Check:** ✅ Zuweisung transparent

---

**📋 CHECKPOINT PHASE 1:**
- [ ] Alle 5 Task-Typen erfolgreich erstellt
- [ ] 1 Assignment mit allen Tasks angelegt
- [ ] Assignment einem Student zugewiesen
- [ ] Aktiviert und ready für Student-Test

**→ Erst wenn alle Checkpoints ✅, dann Phase 2 starten!**

---

# PHASE 2: STUDENT-WORKFLOWS (Jetzt nutzt der Student die erstellten Inhalte)

## S1 – Einstieg: Erste Schritte als Anfänger

**Szenario:** Student hat noch nie mit der IDE gearbeitet

### S1.1 Registrierung und Orientierung
- [ ] Öffne Index/Landing Page
- [ ] **Prüfung:** Ist klar, wofür die IDE da ist? (Lernziel erkennbar?)
- [ ] Klicke "Registrieren"
- [ ] Registriere dich als neuer Student (student@test.local / Password123)
  - **Alternative:** Falls schon registriert, einfach einloggen
- [ ] **Prüfung:** Lande ich automatisch auf Dashboard?
- [ ] **Prüfung:** Sehe ich das zugewiesene "Test-Assignment März 2026"?

**Didaktischer Check:** ✅ Student weiß in <60 Sekunden, was zu tun ist

---

### S1.2 Erste Code-Aufgabe lösen
- [ ] Öffne Assignment "Test-Assignment März 2026"
- [ ] **Prüfung:** Liste zeigt alle 5 Tasks?
- [ ] Wähle erste Aufgabe: "Test: Addition"
- [ ] **Prüfung:** Ist die Aufgabenstellung klar verständlich?
- [ ] **Prüfung:** Ist Code-Template vorgegeben? (x = 5, y = 3)
- [ ] Schreibe: `result = x + y`
- [ ] Klicke "Check" (oder "Submit")
- [ ] **Prüfung:** Erscheint grüne Erfolgsmeldung?
- [ ] **Prüfung:** Wird Lösung als "bestanden" markiert?
- [ ] Wechsle zu nächster Aufgabe ("Test: Multiplikation")
- [ ] **Prüfung:** Vorherige Aufgabe bleibt grün/erledigt markiert?

**Didaktischer Check:** ✅ Erfolgserlebnis in <2 Minuten, klares Feedback

---

### S1.3 Mit Fehlern umgehen lernen
- [ ] Bleibe bei Aufgabe "Test: Multiplikation"
- [ ] Schreibe absichtlich falschen Code: `result = a + ` (unvollständig)
- [ ] Klicke "Check"
- [ ] **Prüfung:** Erscheint verständliche Fehlermeldung? (Syntax Error)
- [ ] **Prüfung:** Bleibt mein Code erhalten? (nicht gelöscht)
- [ ] Korrigiere Code: `result = a * b`
- [ ] Klicke erneut "Check"
- [ ] **Prüfung:** Jetzt Erfolgsmeldung?

**Didaktischer Check:** ✅ Fehlertoleranz, konstruktive Rückmeldung

---

### S1.4 Hints nutzen (Lernhilfe)
- [ ] Bleibe bei "Test: Multiplikation" (falls schon gelöst: Reload oder neue Session)
- [ ] Lösche Code (simuliere: "Ich stecke fest")
- [ ] **Prüfung:** Sind Hint-Buttons (Tipp 1, 2, 3) sichtbar?
- [ ] Klicke "Tipp 1"
- [ ] **Prüfung:** Zeigt Hinweis "Nutze den * Operator"?
- [ ] Klicke "Tipp 2"
- [ ] **Prüfung:** Zweiter Hinweis konkreter: "Die Formel lautet: a * b"?
- [ ] Klicke "Tipp 3"
- [ ] **Prüfung:** Dritter Hinweis zeigt fast die Lösung?
- [ ] **Falls vorhanden:** Klicke "Lösung anzeigen"
- [ ] **Prüfung:** Wird Musterlösung angezeigt?
- [ ] **Prüfung:** Bleibt Aufgabe lösbar?

**Didaktischer Check:** ✅ Gestuftes Hilfesystem, kein "Alles oder Nichts"

---

## S2 – Fortgeschritten: Komplexere Aufgaben

**Szenario:** Student hat Basics verstanden, arbeitet an größeren Tasks

### S2.1 Projekt mit Folder-Struktur (idegui)
- [ ] Öffne Task "Test: Mini-Taschenrechner" im Assignment
- [ ] **Prüfung:** Sehe ich Dateibaum (init.py, index.html, style.css)?
- [ ] Klicke auf init.py
- [ ] **Prüfung:** Python-Code mit idegui im Editor?
- [ ] **Prüfung:** Funktion `addieren(trigger)` vorhanden?
- [ ] Klicke "RUN"
- [ ] **Prüfung:** Startet HTML-Preview?
- [ ] **Prüfung:** Sehe ich Input-Felder und Button?
- [ ] Gib Zahlen ein (z.B. 5 und 3)
- [ ] Klicke Button "Addieren" im Preview
- [ ] **Prüfung:** Ergebnis (8) erscheint?
- [ ] Wechsle zu index.html
- [ ] **Prüfung:** HTML-Code korrekt geladen? (kein Python-Code versehentlich)
- [ ] Ändere Button-Text von "Addieren" zu "Rechnen"
- [ ] Klicke "RUN"
- [ ] **Prüfung:** Button-Text in Preview geändert?
- [ ] Wechsle zu style.css
- [ ] Füge hinzu: `button { background: blue; color: white; }`
- [ ] Klicke "RUN"
- [ ] **Prüfung:** Button ist jetzt blau?

**Didaktischer Check:** ✅ Multi-File-Projekte funktionieren, Preview sinnvoll

---

### S2.2 Intelligent Tests (Randomisierung)
- [ ] Öffne Task "Test: Intelligent Multiply"
- [ ] **Prüfung:** Wird mir ein Code-Template mit x und y vorgegeben?
- [ ] **Prüfung:** Haben x und y zufällige Werte? (z.B. x = 3, y = 7)
- [ ] Schreibe Lösung: `result = x * y`
- [ ] Klicke "Check"
- [ ] **Prüfung:** Test bestanden?
- [ ] Lade Seite neu (F5)
- [ ] **Prüfung:** Sind x und y jetzt anders? (Randomisierung aktiv)
- [ ] Löse erneut mit neuen Werten
- [ ] **Prüfung:** Funktioniert mit neuen Zahlen?
- [ ] **Optional:** Teste mit falschem Code (z.B. `result = x + y`)
- [ ] **Prüfung:** Fehlermeldung korrekt?

**Didaktischer Check:** ✅ Randomisierung verhindert Copy-Paste, fördert Verständnis

---

### S2.3 Quiz-Aufgaben
- [ ] Öffne Task "Test: Python Basics Quiz"
- [ ] **Prüfung:** Frage "Was gibt print(2+2) aus?" sichtbar?
- [ ] **Prüfung:** Sind 4 Antwortoptionen (A-D) sichtbar?
- [ ] Wähle richtige Antwort: "4"
- [ ] Klicke "Submit" (oder "Check")
- [ ] **Prüfung:** Sofortfeedback "Korrekt" oder grün markiert?
- [ ] **Falls mehrere Quiz-Fragen:** Öffne nächste Frage
- [ ] Oder: Reload Seite und wähle absichtlich falsche Antwort ("22")
- [ ] **Prüfung:** Feedback "Falsch" oder rot markiert?
- [ ] **Prüfung:** Kann ich nochmal versuchen? (je nach Einstellung)

**Didaktischer Check:** ✅ Quiz-Flow klar, direktes Feedback

---

### S2.4 Check-Limits respektieren
- [ ] Öffne Task "Test: Multiplikation" (hat max_attempts = 3)
- [ ] Schreibe absichtlich falschen Code: `result = a + b` (Addition statt Multiplikation)
- [ ] Klicke "Check" (1. Versuch)
- [ ] **Prüfung:** Counter zeigt "1/3" oder ähnlich?
- [ ] **Prüfung:** Fehlermeldung erscheint?
- [ ] Klicke erneut "Check" mit falschem Code (2. Versuch)
- [ ] **Prüfung:** Counter zeigt "2/3"?
- [ ] Klicke erneut "Check" (3. Versuch)
- [ ] **Prüfung:** Counter zeigt "3/3" + Warnung "Keine Versuche mehr"?
- [ ] **Prüfung:** Check-Button jetzt disabled/grau?
- [ ] **Prüfung:** Kann ich "Lösung anzeigen" oder "Tipp 3" noch klicken?
- [ ] Schaue Lösung an
- [ ] Korrigiere Code: `result = a * b`
- [ ] **Prüfung:** Kann ich trotzdem noch die richtige Lösung eintippen? (nur Check disabled)

**Didaktischer Check:** ✅ Begrenzung motiviert zum Nachdenken, aber nicht blockierend

---

## S3 – Speichern & Fortschritt verwalten

### S3.1 Code-Fortschritt bleibt erhalten
- [ ] Öffne Task "Test: Addition" (falls schon gelöst: lösche Code manuell)
- [ ] Schreibe Code teilweise: `result = x +` (unvollständig, als würdest du Pause machen)
- [ ] **Prüfung:** Erscheint Asterisk (*) am Task-/File-Namen?
- [ ] Wechsle zu anderer Aufgabe (z.B. "Test: Multiplikation")
- [ ] **Falls Modal erscheint:** Klicke "Save" oder "Discard"
  - **Wenn "Save":** Änderungen sollen gespeichert werden
  - **Wenn "Discard":** Teste danach S3.1 nochmal mit "Save"
- [ ] Kehre zur ersten Aufgabe zurück ("Test: Addition")
- [ ] **Prüfung:** Mein Code noch da? (auch `result = x +`)
- [ ] Logge dich aus
- [ ] Logge dich wieder ein
- [ ] Öffne Assignment → "Test: Addition"
- [ ] **Prüfung:** Code immer noch gespeichert?

**Didaktischer Check:** ✅ Student kann Pause machen, nichts geht verloren

---

### S3.2 Projekt-Fortschritt speichern
- [ ] Öffne Task "Test: Mini-Taschenrechner"
- [ ] Ändere init.py: Ändere Funktionsname zu `berechnen` statt `addieren`
- [ ] Ändere index.html: Passe `data-function="berechnen"` an
- [ ] **Prüfung:** Beide Dateien haben Asterisk (*)? 
- [ ] Klicke "Save All" (oder speichere einzeln)
- [ ] **Prüfung:** Asterisks verschwinden?
- [ ] Gehe zurück zur Projekt-Liste (oder schließe Task)
- [ ] Öffne Task erneut
- [ ] **Prüfung:** Beide Änderungen gespeichert?
- [ ] Ändere nur style.css: `body { background: lightblue; }`
- [ ] Klicke "Save" (nur diese Datei)
- [ ] Öffne andere Datei (z.B. init.py)
- [ ] Zurück zu style.css
- [ ] **Prüfung:** CSS-Änderung erhalten?

**Didaktischer Check:** ✅ Projekt-Arbeit persistiert zuverlässig

---

**📋 CHECKPOINT PHASE 2:**
- [ ] Student kann alle 5 Task-Typen öffnen und bearbeiten
- [ ] Code-Aufgaben mit Check funktionieren
- [ ] Hints sind abrufbar und hilfreich
- [ ] Projekt mit HTML/Python läuft
- [ ] Intelligent Test randomisiert
- [ ] Quiz gibt direktes Feedback
- [ ] Check-Limits greifen
- [ ] Fortschritt wird gespeichert

**→ Wenn alle Checkpoints ✅: Phase 2 abgeschlossen, optional Phase 3 (Edge Cases)**

---

# PHASE 3: ROBUSTHEIT & EDGE CASES (Optional, 30 Min)

## D3 – Dozent: Fortschritt einsehen

### D3.1 Evaluation-Bereich (falls vorhanden)
- [ ] Student hat min. 2 Tasks gelöst, 1 offen
- [ ] Logge dich als Admin ein
- [ ] Öffne Evaluation-Bereich
- [ ] **Prüfung:** Sehe ich Liste der Studenten?
- [ ] Wähle Test-Student aus
- [ ] **Prüfung:** Sehe ich Fortschritt? (2/5 Tasks gelöst)
- [ ] **Prüfung:** Anzahl Check-Attempts sichtbar?
- [ ] **Prüfung:** Kann ich Code-Submissions einsehen?
- [ ] **Optional:** Export-Funktion vorhanden?

**Didaktischer Check:** ✅ Dozent hat Überblick über Lernfortschritt

---

## E1 – Ungültige Eingaben abfangen

### E1.1 Leeren Code submitten (Code Task)
- [ ] Öffne "Test: Addition" als Student
- [ ] Lösche Code komplett (Editor leer)
- [ ] Klicke "Check"
- [ ] **Prüfung:** Error-Message? "Code darf nicht leer sein"
- [ ] **Prüfung:** System stürzt nicht ab?

**Didaktischer Check:** ✅ Hilfreiche Fehlermeldung statt Absturz

---

### E1.2 Ungültigen Python-Code submitten
- [ ] Öffne "Test: Multiplikation"
- [ ] Schreibe: `reult = x * y` (Syntax Error)
- [ ] Klicke "Check"
- [ ] **Prüfung:** Fehlermeldung erscheint?
- [ ] **Prüfung:** Zeigt sie die fehlerhafte Zeile an?
- [ ] **Prüfung:** Check-Counter erhöht sich trotzdem?

**Didaktischer Check:** ✅ Syntax-Fehler werden erkannt, Student lernt daraus

---

### E1.3 Unendlich Hints abrufen
- [ ] Öffne "Test: Multiplikation" (hat 3 Hints)
- [ ] Klicke 3x auf "Hint"
- [ ] **Prüfung:** Nach 3. Hint erscheint: "Keine weiteren Hints"?
- [ ] Versuche 4. Hint-Click
- [ ] **Prüfung:** Button disabled oder Message wiederholt sich?

**Didaktischer Check:** ✅ Hint-System hat klare Grenzen

---

### E1.4 Check-Limit überschreiten
- [ ] Öffne "Test: Multiplikation" (max_attempts = 3)
- [ ] Schreibe **falschen** Code 3x, klicke 3x "Check"
- [ ] **Prüfung:** Nach 3. Check erscheint Limit-Message?
- [ ] **Prüfung:** "Check" Button disabled?
- [ ] **Prüfung:** "Show Solution" Button erscheint?
- [ ] Klicke "Show Solution"
- [ ] **Prüfung:** Musterlösung wird im Editor angezeigt?

**Didaktischer Check:** ✅ Limits verhindern Frustration, zeigen Ausweg

---

## E2 – Browser-Kompatibilität & Performance

### E2.1 Großer Code-Block (Performance)
- [ ] Öffne "Test: Mini-Taschenrechner" (Projekt)
- [ ] Öffne init.py
- [ ] Füge 2000 Zeilen Code ein:
  ```python
  # Line 1
  # Line 2
  ... (bis 2000)
  ```
- [ ] **Prüfung:** Editor bleibt responsive?
- [ ] **Prüfung:** Scrollen funktioniert flüssig?
- [ ] Klicke "Save"
- [ ] **Prüfung:** Speichern erfolgreich? (keine Timeout-Fehler)
- [ ] Öffne andere Datei (index.html)
- [ ] Zurück zu init.py
- [ ] **Prüfung:** Code vollständig geladen?

**Performance Check:** ✅ Große Dateien werden nicht abgeschnitten oder langsam

---

### E2.2 Mehrere Tabs gleichzeitig (Session-Konflikt)
- [ ] Logge dich als Student ein
- [ ] Öffne Assignment "Test-Assignment März 2026"
- [ ] Öffne Task "Test: Addition" in Tab 1
- [ ] Öffne neuen Browser-Tab (beim selben Browser)
- [ ] Logge dich im 2. Tab als Student ein
- [ ] Öffne gleiche Aufgabe ("Test: Addition")
- [ ] **Prüfung:** Beide Tabs zeigen Aufgabe?
- [ ] In Tab 1: Schreibe Code, klicke "Save"
- [ ] Wechsle zu Tab 2
- [ ] **Prüfung:** Änderung von Tab 1 sichtbar? (ggf. nach F5)
- [ ] In Tab 2: Schreibe anderen Code
- [ ] In Tab 1: Lade neu (F5)
- [ ] **Prüfung:** Welche Version wird gespeichert? (Letzter Save gewinnt)

**Session Check:** ✅ Keine Session-Konflikte zwischen Tabs

---

### E2.3 Logout während Task-Bearbeitung
- [ ] Öffne "Test: Intelligent Multiply" als Student
- [ ] Schreibe Code (halb fertig)
- [ ] Öffne neuen Tab, logout (oder Session-Cookie manuell löschen)
- [ ] Zurück zu Task-Tab
- [ ] Klicke "Check"
- [ ] **Prüfung:** Error-Message "Session expired"?
- [ ] **Prüfung:** Redirect zu Login?
- [ ] Logge dich wieder ein
- [ ] **Prüfung:** Code-Änderung ging verloren? (erwartet bei Session-Loss)

**Robustheit Check:** ✅ Logout wird erkannt, User zum Login geleitet

---

### E2.4 Browser-Reload während Save-Modal
- [ ] Öffne "Test: Mini-Taschenrechner"
- [ ] Ändere init.py (Asterisk erscheint)
- [ ] Wechsle zu index.html (Save-Modal erscheint)
- [ ] **Während Modal offen:** Drücke F5 (Hard Reload)
- [ ] **Prüfung:** System stürzt nicht ab?
- [ ] Nach Reload: Öffne Projekt erneut
- [ ] **Prüfung:** Welche Version ist gespeichert? (hängt von Modal-Aktion ab)

**Edge Case Check:** ✅ Modal-Dialoge crashen nicht bei unexpected Reload

---

# ZUSAMMENFASSUNG: TESTPRIORITÄTEN & DURCHFÜHRUNG

## 🔴 KRITISCH (Must-Work für Beta)

**Phase 1 – Dozent erstellt Basis-Content:**
- D1.1 Neue Code-Aufgabe erstellen ("Test: Addition")
- D1.2 Code-Aufgabe mit Hints ("Test: Multiplikation")
- D2.1 Assignment erstellen und zuweisen ("Test-Assignment März 2026")

**Phase 2 – Student nutzt Content:**
- S1.2 Erste Code-Aufgabe lösen ("Test: Addition")
- S1.3 Mit Fehlern umgehen ("Test: Multiplikation")
- S3.1 Code-Fortschritt bleibt erhalten
- S3.2 Projekt-Fortschritt speichern

**→ Diese Tests müssen 100% funktionieren, sonst kein Beta-Launch!**

---

## 🟡 WICHTIG (Should-Work für Beta)

**Phase 1 – Dozent erstellt erweiterten Content:**
- D1.3 Projekt mit Folder-Struktur ("Test: Mini-Taschenrechner")
- D1.4 Intelligent Test mit Randomisierung ("Test: Intelligent Multiply")
- D1.5 Hints und Limits setzen

**Phase 2 – Student nutzt erweiterte Features:**
- S1.4 Hints nutzen (Lernhilfe)
- S2.1 Projekt mit Folder-Struktur
- S2.2 Intelligent Tests mit Randomisierung
- S2.4 Check-Limits werden respektiert

**→ Sollte funktionieren, aber Beta kann mit Einschränkungen starten**

---

## 🟢 NÜTZLICH (Nice-to-Have für Beta)

**Phase 1 – Quiz-Feature:**
- D1.2 Quiz erstellen ("Test: Python Basics Quiz")

**Phase 2 – Student nutzt Quiz:**
- S2.3 Quiz-Aufgaben lösen

**Phase 3 – Evaluation:**
- D3.1 Fortschritt einsehen (Evaluation-Bereich)

**Phase 3 – Edge Cases:**
- E1.1-E1.4 Ungültige Eingaben abfangen
- E2.1-E2.4 Browser-Performance & Session

**→ Wenn Zeit: testen. Sonst: nach Beta-Feedback nachziehen**

---

# DURCHFÜHRUNG: EMPFOHLENE REIHENFOLGE

## ✅ SCHRITT 1: BASIS-SETUP (Phase 1 Minimum – 30 Min)

**Als Admin:**
1. D1.1 – Code-Task erstellen: "Test: Addition"
2. D1.2 – Code-Task mit Hints: "Test: Multiplikation" (3 Hints, max_attempts=3)
3. D2.1 – Assignment erstellen: "Test-Assignment März 2026" (füge beide Tasks hinzu)
4. D2.2 – Assignment dem Test-Student zuweisen

**Checkpoint:** ✅ 2 Tasks erstellt, 1 Assignment zugewiesen

---

## ✅ SCHRITT 2: STUDENT-NUTZUNG TESTEN (Phase 2 Minimum – 30 Min)

**Als Student (student@test.local):**
1. S1.1 – Registrierung und Orientierung
2. S1.2 – Erste Code-Aufgabe lösen ("Test: Addition")
3. S1.3 – Mit Fehlern umgehen (falschen Code bei "Test: Multiplikation")
4. S1.4 – Hints nutzen (3 Hints abrufen)
5. S3.1 – Code-Fortschritt bleibt erhalten (Code speichern, ausloggen, einloggen, Code da?)

**Checkpoint:** ✅ Basis-Lernzyklus funktioniert durchgängig

**→ Wenn Schritt 1 + 2 erfolgreich: System ist Beta-ready für Code-Tasks!**

---

## ✅ SCHRITT 3: ERWEITERTE FEATURES (Phase 1+2 Erweitert – 45 Min)

**Als Admin:**
1. D1.3 – Projekt erstellen: "Test: Mini-Taschenrechner" (init.py + index.html + style.css)
2. D1.4 – Intelligent Test: "Test: Intelligent Multiply" (mit Randomizer)
3. D1.5 – Quiz erstellen: "Test: Python Basics Quiz" (optional)
4. D2.1 – Füge alle 5 Tasks zum Assignment hinzu

**Als Student:**
1. S2.1 – Projekt mit Folder-Struktur ("Test: Mini-Taschenrechner")
2. S2.2 – Intelligent Tests ("Test: Intelligent Multiply" – x/y ändern sich bei Reload?)
3. S2.3 – Quiz lösen (optional, falls erstellt)
4. S2.4 – Check-Limits testen (max_attempts=3 bei "Test: Multiplikation")
5. S3.2 – Projekt-Fortschritt speichern (init.py + index.html ändern, Save All)

**Checkpoint:** ✅ Alle 5 Task-Typen funktionieren

**→ Wenn Schritt 3 erfolgreich: System ist vollständig Beta-ready!**

---

## ✅ SCHRITT 4: EDGE CASES & ROBUSTHEIT (Phase 3 Optional – 30 Min)

**Als Student:**
1. E1.1 – Leeren Code submitten (Error-Message?)
2. E1.2 – Ungültigen Code submitten (Syntax Error erkannt?)
3. E1.3 – Unendlich Hints abrufen (Limit nach 3 Hints?)
4. E1.4 – Check-Limit überschreiten (Button disabled? "Show Solution"?)
5. E2.1 – Großer Code-Block (2000 Zeilen – Editor friert nicht?)
6. E2.2 – Mehrere Tabs (Session-Konflikt? Last-Write-Wins?)
7. E2.3 – Logout während Task (Session Expired → Redirect zu Login?)
8. E2.4 – Browser-Reload während Modal (kein Crash?)

**Als Admin:**
1. D3.1 – Evaluation-Bereich (Fortschritt einsehen, falls vorhanden)

**Checkpoint:** ✅ System ist robust gegen Fehlbedienung

**→ Wenn Schritt 4 erfolgreich: System ist Production-ready!**

---

# ZEITPLAN & PRIORITÄTEN

| Phase | Titel | Dauer | Priorität | Beta-Blocker? |
|-------|-------|-------|-----------|---------------|
| **1+2 Minimum** | Basis-Lernzyklus | 60 Min | 🔴 KRITISCH | Ja |
| **1+2 Erweitert** | Alle Task-Typen | 45 Min | 🟡 WICHTIG | Nein |
| **3 Optional** | Edge Cases | 30 Min | 🟢 NÜTZLICH | Nein |
| **Gesamt** | Vollständiger Test | **135 Min** | - | - |

**Minimum für Beta-Launch:** Phase 1+2 Minimum (60 Min)  
**Empfohlen für Beta-Launch:** Phase 1+2 Minimum + Erweitert (105 Min)  
**Empfohlen für Production:** Alle 3 Phasen (135 Min)

---

# ERGEBNIS-DOKUMENTATION

**Nach jedem Test-Durchlauf:**
1. Markiere alle ✅ Checkboxen in diesem Dokument
2. Notiere Fehler/Bugs in eigene Issue-Liste:
   - **Fehler-Titel** (z.B. "S1.2: Check-Button reagiert nicht")
   - **Reproduzierbarkeit** (Immer / Manchmal / Einmalig)
   - **Priorität** (🔴 Kritisch / 🟡 Wichtig / 🟢 Nützlich)
3. Ergänze Screenshots/Videos bei UI-Fehlern
4. Dokumentiere in [UI-TEST-MANUAL-CHECKLIST.md](UI-TEST-MANUAL-CHECKLIST.md)

**Beta-Launch Kriterien:**
- ✅ Phase 1+2 Minimum: 100% bestanden
- ✅ Keine 🔴 KRITISCHEN Fehler offen
- ✅ Max. 3 🟡 WICHTIGE Fehler offen (dokumentiert)
- ✅ API-Tests: 17/17 bestanden ([API-TEST-REPORT.md](API-TEST-REPORT.md))
- ✅ UI-Smoke-Tests: 22/22 bestanden ([UI-TEST-PLAN.md](UI-TEST-PLAN.md))

---

**Letzte Aktualisierung:** Testplan Version 2.0 – Logische Reihenfolge (Dozent zuerst, dann Student)


