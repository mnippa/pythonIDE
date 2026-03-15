# UI-Testplan (systematisch) – pythonIDE

Stand: 09.03.2026

## 1) Ziel & Scope

Dieser Plan deckt funktionale UI-Tests für die produktiven Seiten in public/ ab:
- Gastbereich (Landing, Login, Register, Free, Share)
- Auth-Bereich (Dashboard, Assignments, Projects, Editor, Evaluation)
- Rollenbereich (Admin)

Nicht in Scope:
- Technische API-Security-Tests (separat bereits durchgeführt)
- Tiefes Browser/Rendering/UX-Polish (visuelle Regression)

## 2) Teststrategie in 3 Stufen

### Stufe A – Smoke (bei jedem Deployment)
Ziel: Schnelle Aussage „Seite lebt, Routing/Redirect korrekt“
- HTTP-Status je Seite
- Redirect-Verhalten je Rolle (Gast/User/Admin)
- Basis-Inhaltsmarker (z. B. Title vorhanden)

### Stufe B – Core User Journeys (pro Release-Kandidat)
Ziel: Kritische Lern-/Arbeitsabläufe sind funktionsfähig
- Registrierung → Login → Dashboard
- Assignments öffnen, zwischen Tasks wechseln
- Projects öffnen, Datei wechseln, speichern
- Editor öffnen und zurück zu Projects

### Stufe C – Regression (vor Beta/Live)
Ziel: Bereiche mit hoher Änderungsrate gegen Seiteneffekte sichern
- Save-Logik/Dirty-States
- Task-Wechsel mit ungespeicherten Änderungen
- Pre-Task-Dialog in Admin
- Rollen- und Zugriffsmatrix

## 3) Rollenmatrix

- Gast: index.php, login.php, register.php, free.php, share.php erreichbar; geschützte Seiten redirect auf login/projects
- User: dashboard.php, assignments.php, projects.php erreichbar; admin.php/evaluation.php für normale User nicht direkt zugänglich
- Admin: zusätzlich admin.php/evaluation.php erreichbar (manuell gegen Admin-Account prüfen)

## 4) Was ich selbst funktional testen kann (automatisierbar)

## 4.1 Bereits live geprüft

### Gastzugriff (GET)
- index.php → 200
- login.php → 200
- register.php → 200
- free.php → 200
- share.php → 200
- dashboard.php → 302 → login.php
- assignments.php → 302 → login.php
- projects.php → 302 → login.php
- editor.php → 302 → projects.php
- admin.php → 302 → login.php
- evaluation.php → 302 → login.php

### User-Session (GET nach Login via API)
- dashboard.php → 200
- assignments.php → 200
- projects.php → 200
- editor.php → 302 → projects.php
- admin.php → 302 → projects.php
- evaluation.php → 302 → projects.php

### Inhaltsmarker (Seitentitel)
- index.php: Python IDE - HS Pforzheim
- login.php: Login - HS PF Python IDE
- free.php: Python IDE
- dashboard.php: Dashboard - HS PF Python IDE
- assignments.php: Python IDE - Meine Aufgaben
- projects.php: Python IDE - Meine Projekte

Hinweis: register.php/share.php haben aktuell keinen title-Marker.

## 4.2 Zusätzlich automatisierbar (nächster Schritt)
- Vorhandensein zentraler UI-Elemente im HTML (Formfelder, Buttons, Container)
- Redirect-Assertions für alle Rollen in einer festen Matrix
- Login/Logout/Session-Flow als Smoke-Job
- Basale Content-Checks (Textmarker, erwartete Navigation)

## 5) Was manuell im Browser getestet werden muss

Diese Punkte sind funktional, aber nicht zuverlässig per einfacher HTTP-Prüfung abdeckbar:
- Monaco-Editor Interaktionen (Tippen, Cursor, Auto-Complete)
- Unsaved-Change-Dialoge (Save/Discard/Cancel) und Fokus-Verhalten
- Dateibaum-Interaktion (expand/collapse, Dateiwechsel)
- Pre-Task-Dialog UX inkl. Enter-Key und Disabled-Felder
- Button-Enable/Disable im UI (z. B. Check-Limits)
- Pyodide-Run und Ergebnisdarstellung im Frontend

## 6) Systematische Testfälle (manuell)

### Suite M1 – Auth & Navigation
- M1.1 Login mit gültigem User
- M1.2 Login mit ungültigem Passwort (Fehlertext)
- M1.3 Logout und Rücksprungverhalten
- M1.4 Direkter URL-Aufruf geschützter Seiten als Gast

### Suite M2 – Assignments
- M2.1 Assignment-Liste laden
- M2.2 Task öffnen und Inhalte prüfen
- M2.3 Task-Wechsel mit ungespeicherten Änderungen (Modal)
- M2.4 Save-Verhalten pro Task/File

### Suite M3 – Projects/Editor
- M3.1 Projekt öffnen, Dateiwechsel (init.py ↔ index.html)
- M3.2 Dirty-Marker (*) korrekt setzen/entfernen
- M3.3 Save-All vs. Einzel-Save
- M3.4 Rückkehr von editor.php zu projects.php

### Suite M4 – Admin (mit Admin-User)
- M4.1 Pre-Task-Dialog öffnet vor Task-Form
- M4.2 Type/Title nach Confirm gesperrt
- M4.3 Task speichern und erneut öffnen
- M4.4 Rollenprüfung (User darf Admin nicht öffnen)

## 7) Exit-Kriterien je Phase

### Phase 1 (Smoke/API + Basis-UI)
- Alle Stufe-A Smoke-Checks grün
- Keine kritischen Redirect-/Routing-Fehler

### Phase 2 (Beta)
- M1–M4 ohne Blocker
- Keine Datenverlust-Bugs in Save/Task-Wechsel

### Phase 3 (Live)
- Regressionssuite erneut grün
- Offene Punkte nur low-priority UX-Feinschliff

## 8) Praktischer Ausführungsrhythmus

- Bei jedem Merge: Stufe A automatisch
- Täglich im Betatest: M1 + M2 Kurzlauf
- Vor Release: Voller Lauf M1–M4 + Stufe A

## 9) Konkrete nächste Umsetzung

1. PowerShell-Skript UI-SMOKE erweitern (Status + Redirect + Title + Marker)
2. Manuelle Checklist-Datei mit Pass/Fail-Spalten ergänzen
3. Optional: später Playwright für echte Browser-Interaktion (Monaco/Modal-Flows)
