# DB + UML Umsetzungscheckliste (Schrittweise)

Status: Arbeitsdokument
Strategie: Erst DB Ende-zu-Ende (Projekt + Assignment), dann UML Ende-zu-Ende.

## 1) Leitlinien (fix vor Umsetzung)

- Entkopplung pro Typ: `code`, `gui`, `db`, `uml` jeweils eigener Renderer/Controller.
- Keine Cross-Mode-Logik in Einzel-Features (kein verborgenes `if html|mixed` fuer DB/UML).
- Assignment-Typen bleiben explizit getrennt (`db_model`, spaeter `uml_model`, etc.).
- Jede Phase hat ein Go/No-Go Gate mit klaren Abnahmekriterien.

---

## 2) Reihenfolge (verbindlich)

1. Phase A: Architektur-Fixierung und harte Trennung der Modi
2. Phase B: DB-Projekteditor komplett
3. Phase C: DB-Assignments komplett
4. Phase D: DB-Haertung (Regression, Import/Export, UX)
5. Phase E: UML-Projekteditor grafisch (MVP)
6. Phase F: UML-Assignments (MVP)
7. Phase G: Vereinheitlichung, Doku, Betriebsreife

---

## 3) Checkliste nach Phasen

## Phase A: Architektur-Fixierung

### A1. Mode-Matrix dokumentieren
- [x] Matrix erstellen: Typ -> Mode -> benoetigte Container -> API-Endpunkte -> Persistenzdateien
- [x] Sichtbarkeitsregeln je Mode definieren (Output, GUI, Designer, Hilfe)
- [x] Single Source of Truth benennen (zentrale Resolver/Config)

Abnahme:
- [x] Ein Dokument beschreibt alle Modi konsistent und ohne Sonderfaelle.
- [x] Neue Modi koennen ohne globale `if`-Kaskaden eingesteckt werden.

#### A1 Ergebnis: Projekt-Mode-Matrix (verbindlich)

Single Source of Truth (aktuell):
- Frontend Resolver + Config in `public/js/projects-editor.js` (`resolveProjectMode`, `getProjectModeConfig`).

| Projekttyp (DB-Wert) | Mode | Sichtbare Kern-Container | Spezielle UI | API-Schwerpunkt | Persistenz |
| --- | --- | --- | --- | --- | --- |
| `python` | `code` | Editor, Output, Plot | kein GUI-Stage, kein Web-Hilfebutton | `api/projects/*`, `api/project_files/*` | `init.py` (+ optionale Dateien) |
| `html`, `mixed` | `gui` | Editor, GUI-Container, Output, Plot | idegui-Hilfe sichtbar, HTML-Renderflow aktiv | `api/projects/*`, `api/project_files/*` | `init.py`, `index.html`, Assets |
| `db_small` | `db` | Editor, DB-Stage (im GUI-Container), Output optional | eigener DB-Renderer, keine HTML-GUI-Abhaengigkeit | `api/projects/*`, `api/project_files/*` | `db_model.json`, `db_export.sql`, `init.py` |
| `uml` (geplant) | `uml` | Editor, UML-Stage (im GUI-Container), Output optional | eigener UML-Renderer | `api/projects/*`, `api/project_files/*` | `uml_model.json`, `init.py` |

Container-Regeln (Projektmodus):
- `code`: GUI-Container aus, Right-Panel nicht im GUI-Layout.
- `gui`: GUI-Container an, Right-Panel GUI-Layout an, HTML-Renderzustand aktiv.
- `db`: GUI-Container an, aber DB-Stage exklusiv; keine HTML-Renderzustandsflags verwenden.
- `uml`: GUI-Container an, aber UML-Stage exklusiv; keine HTML-Renderzustandsflags verwenden.

#### A1 Ergebnis: Assignment-Typ-Matrix (verbindlich)

| Tasktyp | Renderer (Frontend) | Submit/Check (Backend) | Statusziel | Persistenz Antwort |
| --- | --- | --- | --- | --- |
| `code` | Assignments Code-Editor | Code-Submit/Test Endpunkte | `passed`/`failed`/`in_progress` | `user_tasks.current_code` |
| `code_reading` | Quiz Renderer (Code Reading) | `api/user_tasks/submit_quiz.php` | iterativ bis `passed`/`failed` | `user_tasks.text_answer`, `iteration_values` |
| `single_choice`, `multiple_choice` | Quiz Renderer (Choice) | `api/user_tasks/submit_quiz.php` | `passed`/`failed`/`in_progress` | `selected_options` |
| `free_text` | Quiz Renderer (Text) | `api/user_tasks/submit_quiz.php` | `passed`/`failed`/`in_progress` | `text_answer` |
| `db_model` | Quiz Renderer (derzeit Text-MVP, spaeter DB-Canvas) | `api/user_tasks/submit_quiz.php`, `api/user_tasks/test_submission.php` | `submitted` (manual review) | `text_answer` (MVP), spaeter Struktur-JSON |
| `uml_model` (geplant) | eigener UML Renderer | eigener Check/Submit-Flow oder Quiz-Erweiterung | vorauss. `submitted` + optional Auto-Check | `text_answer` oder `uml_model` JSON |

Statusregeln (Assignment):
- `submitted` gilt als abgeschlossen fuer Assignment-Fortschritt, aber nicht als fachlich `passed`.
- `db_model` und spaeter `uml_model` bleiben review-faehig, ohne Code/UI-Typen zu beeinflussen.

Offene Architektur-Entscheidungen fuer A2/B1:
- Ob `db_model` kurzfristig bei Text-MVP bleibt oder direkt auf Struktur-Canvas wechselt.
- Ob `uml_model` als eigener Submit-Endpunkt kommt oder in bestehende Quiz-Submit-Logik integriert wird.

### A2. Frontend-Struktur absichern
- [x] Je Mode separaten Entry-Handler definieren (code/gui/db/uml)
- [x] Gemeinsame Hilfsfunktionen nur neutral halten (keine Typ-Annahmen)
- [x] Feature Flags/Platzhalter fuer DB/UML klar markieren

Abnahme:
- [x] Wechsel zwischen Projekttypen erzeugt keine unerwarteten Container-Reste.

Gate A -> B:
- [x] Architektur reviewt und freigegeben.

---

## Phase B: DB-Projekteditor komplett

### B1. Datenmodell und Persistenz
- [x] `db_model.json` Schema versionieren (inkl. Migrationstrategie)
- [x] Stabiler Serializer/Deserializer (Load/Save) implementiert
- [x] SQL-Export aus Modell deterministisch erzeugen

Abnahme:
- [x] Modell laden/speichern ist verlustfrei.
- [x] SQL-Export ist reproduzierbar fuer identisches Modell.

### B2. Editor-Funktionen
- [x] Tabellen: erstellen, umbenennen, loeschen
- [x] Spalten: Name, Datentyp, Pflicht, Default
- [x] Schluessel: PK/FK setzen/entfernen
- [ ] Beziehungen modellieren (mind. 1:n und n:m via Zwischentabelle)

Abnahme:
- [x] Vollstaendiges Mini-Datenmodell kann ohne Workaround erstellt werden.

### B3. Validierung und UX
- [x] Sofortvalidierung bei inkonsistenten FKs
- [x] Verstaendliche Fehlermeldungen (nicht technisch, sondern fachlich)
- [x] Undo/Redo fuer Kernaktionen (mind. 1 Stufe)

Abnahme:
- [x] Unzulaessige Modelle koennen nicht unbemerkt gespeichert werden.

Gate B -> C:
- [x] DB-Projekteditor ist fuer Lehrbetrieb nutzbar.

Smoke-Test (2026-07-25):
- [x] UI-Smoke auf mehreren `db_small` Projekten ausgefuehrt (Projekt IDs 50 und 51).
- [x] DB-Designer wurde jeweils geladen (Toolbar + Tabelleneditor vorhanden).
- [x] Save-Flow verifiziert (`db_model.json` + `db_export.sql` wurden geschrieben).
- [x] Validierungsblockade verifiziert (ungueltige FK-Konfiguration blockiert Speichern).

Smoke-Test (2026-07-28):
- [x] SQL Editor startet standardmaessig leer und bietet Query-Ausfuehrung mit Play-Button.
- [x] Query-Ergebnisse werden im rechten Panel als Ergebnisraster angezeigt.
- [x] Datenraster unterstuetzt Inline-Editing inkl. Enter-basierter Inline-Neuzeile.
- [x] Datenraster unterstuetzt Zeile duplizieren/loeschen und Tastatur-Navigation mit Pfeiltasten.

---

## Phase C: DB-Assignments komplett

### C1. Authoring (Admin)
- [ ] `db_model` Task-Form finalisieren (Pflichtfelder, Vorlagen, Hinweise)
- [ ] Sollstruktur-Regeln fuer Bewertung festlegen (was ist strikt, was tolerant)
- [ ] Import/Export fuer `db_model` validieren

Abnahme:
- [ ] Lehrperson kann DB-Aufgabe ohne JSON-Handarbeit erstellen.

### C2. Studentenansicht
- [ ] DB-Task-Renderer finalisieren (MVP: Text + Strukturbezug oder Canvas)
- [ ] Speichern von Zwischenstand (Draft) sichern
- [ ] Finale Abgabe mit Statusfluss absichern

Abnahme:
- [ ] Studierende koennen Aufgabe bearbeiten, speichern, abgeben, wieder einsehen.

### C3. Bewertung
- [ ] Auto-Check fuer Struktur (Tabellen/Felder/PK/FK)
- [ ] Teilfeedback (z. B. Tabellen, Felder, Beziehungen)
- [ ] Manueller Review-Fallback bleibt aktiv

Abnahme:
- [ ] Ergebnis ist nachvollziehbar und reproduzierbar.

Gate C -> D:
- [ ] DB-Ende-zu-Ende (Admin + Student + Bewertung) abgeschlossen.

---

## Phase D: DB-Haertung

### D1. Tests
- [ ] API-Tests fuer DB-Submit/Status
- [ ] UI-Smoke fuer Projektmodus und Assignmentmodus
- [ ] Regressionsliste fuer bekannte Kantenfaelle

### D2. Betrieb
- [ ] Performance-Smoketest (groessere Modelle)
- [ ] Fehlerprotokollierung fuer DB-Aktionen
- [ ] Rollback-Plan fuer kritische Releases

Abnahme:
- [ ] Keine Blocker in Kernflows, akzeptable Performance im Zielsetup.

Gate D -> E:
- [ ] DB als stabil markiert.

---

## Phase E: UML-Projekteditor grafisch (MVP)

### E1. UML-Scope begrenzen
- [ ] Klassenname, Attribute, Beziehungen, Kardinalitaeten
- [ ] Vererbung optional als zweite Stufe
- [ ] Notation vereinheitlichen (MVP-Regeln)

### E2. Persistenz
- [ ] `uml_model.json` als eigenes Schema (keine Vermischung mit DB)
- [ ] Versionierung/Validierung analog DB

### E3. Editor
- [ ] Klasse erstellen/verbinden/loeschen
- [ ] Kardinalitaet pflegen
- [ ] Grundlayout und Lesbarkeit

Abnahme:
- [ ] UML-Diagramm kann erstellt, gespeichert, geladen werden.

Gate E -> F:
- [ ] UML-Projekteditor MVP abgenommen.

---

## Phase F: UML-Assignments (MVP)

### F1. Tasktyp und Authoring
- [ ] `uml_model` Tasktyp einfuehren
- [ ] Admin-Form und Import/Export erweitern
- [ ] Aufgaben-Vorlagen fuer typische UML-Lernszenarien

### F2. Studenten-Renderer
- [ ] UML-Canvas im Assignment
- [ ] Zwischenstand speichern
- [ ] Finale Abgabe + Statusfluss

### F3. Bewertung
- [ ] Strukturchecker (Klassen/Beziehungen/Kardinalitaet)
- [ ] Teilfeedback
- [ ] Manueller Review-Fallback

Abnahme:
- [ ] UML-Aufgabe funktioniert Ende-zu-Ende.

Gate F -> G:
- [ ] UML-MVP stabil im Lehrbetrieb.

---

## Phase G: Vereinheitlichung und Betriebsreife

### G1. Gemeinsame Standards
- [ ] Einheitliches Fehlerformat API-seitig
- [ ] Einheitliches Feedbackformat fuer Checker
- [ ] Gemeinsame Logging-Konvention

### G2. Dokumentation
- [ ] Admin-Leitfaden (Aufgabe anlegen, pruefen, bewerten)
- [ ] Technik-Leitfaden (neuen Typ hinzufuegen)
- [ ] Kurz-Runbook fuer typische Stoerungen

Abnahme:
- [ ] Team kann neue Typen ohne Architekturbruch erweitern.

---

## 4) Ticket-Vorlage (fuer jede Umsetzungseinheit)

Titel:
- [ ] Klarer Scope, genau ein Ziel

Technik:
- [ ] Betroffene Dateien
- [ ] API/Schema-Aenderungen
- [ ] Migrationsbedarf

Tests:
- [ ] Positivfall
- [ ] Negativfall
- [ ] Regressionfall

Abnahme:
- [ ] Fachlich akzeptiert
- [ ] Technisch akzeptiert
- [ ] Doku aktualisiert

---

## 5) Startpunkt fuer den naechsten Schritt

Empfehlung fuer den direkten Start:
1. A1 Matrix finalisieren
2. A2 Handler-Schnitt finalisieren
3. B1 Schema/Version fuer `db_model.json` festzurren

Wenn diese drei Punkte stehen, gehen wir in B2 und arbeiten den DB-Projekteditor kontrolliert von oben nach unten ab.
