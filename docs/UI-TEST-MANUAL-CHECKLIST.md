# Manuelle UI-Test-Checklist - pythonIDE

Version: 1.0  
Datum: 09.03.2026

## Vorbereitung
- [ ] Browser: Chrome/Firefox/Edge
- [ ] Testuser vorhanden (nicht Admin)
- [ ] Admin-User vorhanden
- [ ] Lokaler Server läuft (XAMPP/Apache)

---

## M1 – Authentication & Navigation

### M1.1 Login mit gültigem User
- [ ] Öffne http://localhost/pythonIDE/public/login.php
- [ ] Gib gültige E-Mail und Passwort ein
- [ ] Klicke "Anmelden"
- [ ] **Erwartung:** Weiterleitung zu dashboard.php
- [ ] **Erwartung:** User-Name im Header sichtbar

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M1.2 Login mit ungültigem Passwort
- [ ] Öffne login.php
- [ ] Gib gültige E-Mail + falsches Passwort ein
- [ ] Klicke "Anmelden"
- [ ] **Erwartung:** Fehlermeldung "Invalid email or password"
- [ ] **Erwartung:** Kein Redirect, bleibe auf login.php

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M1.3 Logout und Rücksprung
- [ ] Logge dich ein
- [ ] Navigiere zu Dashboard oder Projects
- [ ] Klicke "Logout" im Header
- [ ] **Erwartung:** Weiterleitung zu login.php
- [ ] Versuche manuell dashboard.php aufzurufen
- [ ] **Erwartung:** Redirect zu login.php (Session ungültig)

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M1.4 Direkter URL-Aufruf geschützter Seiten als Gast
- [ ] Logout (oder Inkognito-Fenster)
- [ ] Versuche direkt dashboard.php aufzurufen
- [ ] **Erwartung:** Redirect zu login.php
- [ ] Versuche direkt projects.php aufzurufen
- [ ] **Erwartung:** Redirect zu login.php
- [ ] Versuche direkt admin.php aufzurufen
- [ ] **Erwartung:** Redirect zu login.php

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## M2 – Assignments (Aufgaben)

### M2.1 Assignment-Liste laden
- [ ] Logge dich ein
- [ ] Navigiere zu "Aufgaben" (assignments.php)
- [ ] **Erwartung:** Liste der zugewiesenen Aufgaben sichtbar
- [ ] **Erwartung:** Titel, Beschreibung, Anzahl Tasks pro Assignment

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M2.2 Task öffnen und Inhalte prüfen
- [ ] Klicke auf ein Assignment
- [ ] **Erwartung:** Task-Liste links erscheint
- [ ] Klicke auf ersten Task
- [ ] **Erwartung:** Task-Beschreibung rechts sichtbar
- [ ] **Erwartung:** Code-Editor oder Input-Felder sichtbar (je nach Task-Typ)
- [ ] **Erwartung:** "Check" oder "Submit" Button vorhanden

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M2.3 Task-Wechsel mit ungespeicherten Änderungen
- [ ] Öffne Task mit Code-Editor oder Folder-Struktur
- [ ] Ändere Inhalt (tippe etwas im Editor)
- [ ] **Erwartung:** Asterisk (*) erscheint am Task-/File-Namen
- [ ] Klicke auf anderen Task ohne zu speichern
- [ ] **Erwartung:** Modal-Dialog erscheint: "Save / Discard / Cancel"
- [ ] Klicke "Cancel"
- [ ] **Erwartung:** Bleibe beim aktuellen Task, Änderungen erhalten
- [ ] Klicke erneut auf anderen Task
- [ ] Klicke "Discard"
- [ ] **Erwartung:** Wechsel zum neuen Task, Änderungen verworfen
- [ ] Wiederhole Test mit "Save"
- [ ] **Erwartung:** Wechsel zum neuen Task, Änderungen gespeichert

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M2.4 Save-Verhalten pro Task/File
- [ ] Öffne Folder-Structure Task (z.B. HTML-Projekt)
- [ ] Ändere init.py
- [ ] **Erwartung:** Asterisk bei init.py
- [ ] Klicke "Save" Button
- [ ] **Erwartung:** Asterisk verschwindet
- [ ] Ändere index.html
- [ ] **Erwartung:** Asterisk bei index.html
- [ ] Klicke "Save All"
- [ ] **Erwartung:** Alle Asterisks verschwinden

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## M3 – Projects/Editor

### M3.1 Projekt öffnen, Dateiwechsel
- [ ] Navigiere zu "Projekte" (projects.php)
- [ ] Klicke auf ein Projekt mit Folder-Struktur
- [ ] **Erwartung:** Editor öffnet sich
- [ ] **Erwartung:** Dateibaum links sichtbar
- [ ] Klicke auf init.py
- [ ] **Erwartung:** Init.py Inhalt im Editor
- [ ] Klicke auf index.html
- [ ] **Erwartung:** Index.html Inhalt im Editor
- [ ] **Erwartung:** Editor-Inhalt tauscht korrekt (kein stuck content)

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M3.2 Dirty-Marker (*) korrekt setzen/entfernen
- [ ] Öffne Projekt
- [ ] Ändere init.py Inhalt
- [ ] **Erwartung:** Asterisk bei init.py
- [ ] Speichere via "Save" Button
- [ ] **Erwartung:** Asterisk verschwindet
- [ ] Ändere index.html
- [ ] **Erwartung:** Asterisk bei index.html
- [ ] Ändere style.css
- [ ] **Erwartung:** Asterisk bei style.css
- [ ] **Erwartung:** Beide Asterisks parallel sichtbar

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M3.3 Save-All vs. Einzel-Save
- [ ] Ändere 3 Dateien (init.py, index.html, style.css)
- [ ] **Erwartung:** 3 Asterisks sichtbar
- [ ] Klicke "Save All"
- [ ] **Erwartung:** Alle 3 Asterisks verschwinden
- [ ] Ändere erneut 2 Dateien
- [ ] Klicke nur bei einer Datei auf "Save" (einzeln)
- [ ] **Erwartung:** Nur dieser Asterisk verschwindet, anderer bleibt

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M3.4 Rückkehr von editor.php zu projects.php
- [ ] Öffne Projekt im Editor
- [ ] Klicke "Zurück zu Projekte" oder Browser-Back
- [ ] **Erwartung:** Rückkehr zu projects.php
- [ ] **Erwartung:** Projektliste noch sichtbar
- [ ] Öffne anderes Projekt
- [ ] **Erwartung:** Editor öffnet neues Projekt korrekt

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## M4 – Admin (nur mit Admin-User)

### M4.1 Pre-Task-Dialog öffnet vor Task-Form
- [ ] Logge dich als Admin ein
- [ ] Navigiere zu admin.php
- [ ] Klicke "Neue Aufgabe" Button
- [ ] **Erwartung:** Pre-Task-Dialog erscheint
- [ ] **Erwartung:** Type-Dropdown sichtbar
- [ ] **Erwartung:** Title-Input sichtbar
- [ ] **Erwartung:** "Weiter" Button sichtbar

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M4.2 Type/Title nach Confirm gesperrt
- [ ] Wähle Type "Code"
- [ ] Gib Title "Test Task" ein
- [ ] Klicke "Weiter"
- [ ] **Erwartung:** Hauptformular erscheint
- [ ] **Erwartung:** Type-Feld ist disabled (grau)
- [ ] **Erwartung:** Title-Feld ist disabled (grau)
- [ ] **Erwartung:** Andere Felder sind editierbar

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M4.3 Task speichern und erneut öffnen
- [ ] Erstelle neue Aufgabe via Pre-Dialog
- [ ] Fülle Felder aus (Beschreibung, Code Template, etc.)
- [ ] Klicke "Aufgabe speichern"
- [ ] **Erwartung:** Success-Meldung
- [ ] Lade Seite neu (F5)
- [ ] Suche gespeicherte Aufgabe in Liste
- [ ] Klicke zum Bearbeiten
- [ ] **Erwartung:** Alle Daten korrekt geladen
- [ ] **Erwartung:** Type/Title immer noch disabled

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M4.4 Rollenprüfung (User darf Admin nicht öffnen)
- [ ] Logge dich als normaler User (nicht Admin) ein
- [ ] Versuche direkt admin.php aufzurufen
- [ ] **Erwartung:** Redirect zu projects.php ODER 403 Error
- [ ] **Erwartung:** Kein Zugriff auf Admin-Interface

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## M5 – Monaco Editor & Pyodide (Optional)

### M5.1 Monaco Auto-Complete
- [ ] Öffne Code-Editor
- [ ] Tippe "pri" im Python-Editor
- [ ] **Erwartung:** Autocomplete-Dropdown erscheint mit "print"
- [ ] Wähle "print" via Enter
- [ ] **Erwartung:** "print()" wird eingefügt

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

### M5.2 Pyodide-Run und Output
- [ ] Öffne Python-Projekt oder Free Editor
- [ ] Schreibe: `print("Hello World")`
- [ ] Klicke "Run" Button
- [ ] **Erwartung:** "Hello World" erscheint im Output-Bereich
- [ ] Schreibe: `1/0` (Division by Zero)
- [ ] Klicke "Run"
- [ ] **Erwartung:** Error-Nachricht im Output (ZeroDivisionError)

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## M6 – Check-Button Limits (Regression)

### M6.1 Check-Count Limit erreichen
- [ ] Öffne Task mit max_attempts = 3 (z.B. Task 133)
- [ ] Klicke 3x "Check" (mit falschem Code)
- [ ] **Erwartung:** Nach 3. Versuch erscheint Hinweis "Keine Versuche mehr"
- [ ] **Erwartung:** Check-Button ist disabled
- [ ] **Erwartung:** Counter zeigt "3/3"

**Status:** ⬜ Pass ⬜ Fail ⬜ N/A  
**Notizen:**

---

## Gesamtergebnis

**Datum:** _______________  
**Tester:** _______________  
**Browser:** _______________  
**Passed:** ___ / ___  
**Failed:** ___ / ___  

**Kritische Blocker:**
- 

**Kleinere Issues:**
- 

**Notizen:**
- 
