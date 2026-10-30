# DB + UML Umsetzungscheckliste (Schrittweise)

Status: Arbeitsdokument, an den aktuellen Code- und Aufgabenstand angepasst
Strategie: Den DB-Designer als ersten Ende-zu-Ende-Pilot für Projekte und Aufgaben absichern. UML-Projekt- und Aufgabenmodus verwenden dieselben fachlichen Prinzipien, bleiben aber getrennte Modi.

## 1) Leitlinien (fix vor Umsetzung)

- Entkopplung pro Typ: `code`, `gui`, `db`, `uml` jeweils eigener Renderer/Controller.
- Keine Cross-Mode-Logik in Einzel-Features (kein verborgenes `if html|mixed` fuer DB/UML).
- Assignment-Typen bleiben explizit getrennt (`db_model`, `uml`).
- Jede Phase hat ein Go/No-Go Gate mit klaren Abnahmekriterien.

---

## 2) Reihenfolge (verbindlich)

1. Phase A: Architektur-Fixierung und harte Trennung der Modi
2. Phase B: DB-Projekteditor komplett
3. Phase C: DB-Assignments mit lernorientiertem Struktur-Checker
4. Phase D: DB-Haertung (Regression, Import/Export, UX)
5. Phase E: UML-Projekteditor als eigener Projektmodus
6. Phase F: UML-Assignments mit demselben Feedback-Grundprinzip
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
| `db_model` | DB-Modell-Canvas | `api/user_tasks/submit_quiz.php`, `api/user_tasks/test_submission.php` | `submitted` (aktuell manuelle Bewertung) | JSON in `text_answer` |
| `uml` | UML-Canvas (`UmlRenderer`) | Quiz-Submit/Test-Flow | `submitted` (aktuell manuelle Bewertung) | UML-JSON in `text_answer` |

Statusregeln (Assignment):
- `submitted` gilt als abgeschlossen fuer Assignment-Fortschritt, aber nicht als fachlich `passed`.
- `db_model` und `uml` bleiben review-faehig, ohne Code/UI-Typen zu beeinflussen.

Aktueller Stand:
- Beide Aufgaben-Canvas existieren bereits. Die Matrix beschreibt den implementierten Einstieg, nicht die noch offene Bewertungslogik.
- `db_model` und `uml` sind getrennte Aufgabentypen; `uml_model` ist derzeit kein eigener Aufgaben-Typ.
- Projekt- und Aufgabenmodus duerfen neutrale Modellierungsbausteine wiederverwenden, aber weder Persistenz noch Bewertung unbemerkt miteinander koppeln.

### A2. Frontend-Struktur absichern
- [x] Je Mode separaten Entry-Handler definieren (code/gui/db/uml)
- [x] Gemeinsame Hilfsfunktionen nur neutral halten (keine Typ-Annahmen)
- [x] Feature Flags/Platzhalter fuer DB/UML klar markieren

Abnahme:
- [x] Wechsel zwischen Projekttypen erzeugt keine unerwarteten Container-Reste.

Gate A -> B:
- [x] Architektur reviewt und freigegeben.

### A3. Vertrag fuer lernorientierte Modell-Checks
- [ ] Bewertungsrubrik speichert Soll-Elemente mit stabilen internen IDs/Rollen, getrennt von den im Diagramm angezeigten Namen.
- [ ] Namen von Klassen/Tabellen und Attributen/Feldern sind standardmaessig keine exakten Muss-Kriterien.
- [ ] Der Checker ordnet Studentenelemente anhand von Struktur, Beziehungen, Datentypen und Schluesselrollen zu; Namen duerfen hoechstens ein konfigurierbares Zusatzsignal sein.
- [ ] Bei mehrdeutiger Zuordnung wird kein falsches definitives Durchfallen erzeugt: Check als unklar markieren und manuelle Bewertung anbieten.
- [ ] Feedback nennt die konkrete fehlerhafte Studentenstruktur und erklaert die fachliche Regel, ohne Soll-Namen, IDs, Referenzmodell oder Loesungswerte offenzulegen.
- [ ] Feedback ist in unabhaengige Bereiche gegliedert: Elemente, Attribute/Felder, Datentypen, Schluessel/Referenzen, Kardinalitaeten und abgeleitete Werte.

Abnahmekriterien:
- [ ] Umbenannte, fachlich passende Tabellen/Klassen und Felder koennen bestehen.
- [ ] Eine falsche Kardinalitaet wird an der betroffenen Beziehung lokalisiert und fachlich erklaert, z. B. welche Seite genau ein bzw. mehrere Elemente erlaubt.
- [ ] Fehlende, zusaetzliche oder fachlich unpassende Elemente werden getrennt rueckgemeldet; Feedback verrät nicht die erwarteten Namen.
- [ ] Gleiche Modelle mit anderer Reihenfolge oder anderen Namen erhalten bei gleicher Struktur dasselbe fachliche Ergebnis.
- [ ] Mehrdeutige Modelle werden nachvollziehbar zur manuellen Pruefung markiert.

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
- [ ] `db_model` Task-Form fuer eine UML-zu-relationales-Schema-Aufgabe vervollstaendigen: Aufgabenbeschreibung/Quellmodell, erlaubte Datentypfamilien, Schluesselregeln und Bewertungsrubrik.
- [ ] Rubrikeditor von der studentischen Loesungsansicht trennen; erwartete Elemente und Rollen duerfen nicht in Feedback oder Student-API-Daten landen.
- [ ] Pro Rubrikregel festlegen, ob sie strikt, tolerant oder manuell zu bewerten ist. Dazu gehoeren alternative Schluessel, Datentypfamilien und berechnete/gespeicherte Attribute.
- [ ] Akzeptierte Namensvarianten optional als Lehrkraft-Metadaten pflegen; keine automatische Ablehnung allein wegen abweichender Bezeichnungen.
- [ ] Import/Export von Aufgaben samt Rubrik validieren und gegen ungewollte Offenlegung absichern.

Abnahme:
- [ ] Lehrperson kann eine DB-Aufgabe samt Bewertungsrubrik ohne manuelle JSON-Bearbeitung erstellen oder gezielt eine validierte Vorlage verwenden.

### C2. Studentenansicht
- [ ] Vorhandenen `db_model`-Canvas fuer Tabellen, Felder, Datentypen, PK und FK als Antwort auf ein UML-Quellmodell einsetzen.
- [ ] Studenten duerfen fachlich passende eigene Namen fuer Tabellen und Felder waehlen; Soll-Namen werden nicht als UI-Vorgabe eingeblendet.
- [ ] Zwischenstand und erneutes Oeffnen pruefen; Abgabe bleibt bis zur Bewertung als `submitted` review-faehig.
- [ ] Aufgabenmodus darf DB-Projektdateien und Projekt-SQL nicht veraendern.

Abnahme:
- [ ] Studierende koennen ein relationales Schema erstellen, speichern, erneut oeffnen und abgeben.

### C3. Bewertung
- [ ] Soll- und Ist-Modell anhand interner fachlicher Rollen statt exakter sichtbarer Namen abgleichen.
- [ ] Zuordnung bei Umbenennungen deterministisch pruefen: Graphstruktur, Attribute/Feldrollen, Datentypen und Schluesselbeziehungen; Ambiguitaet explizit erkennen.
- [ ] Beziehungen, PK/FK, Kardinalitaeten und Regelverletzungen einzeln pruefen.
- [ ] Teilfeedback erklaert fachliche Fehler, etwa eine falsche Kardinalitaet auf der konkreten Seite der studentischen Beziehung, ohne Soll-Namen zu verraten.
- [ ] Abgeleitete Werte wie Positions-/Gesamtsummen gemaess Rubrik als berechnet oder explizit gespeichert akzeptieren.
- [ ] Manuellen Review-Fallback bei Mehrdeutigkeit oder fachlich vertretbarer, nicht hinterlegter Alternative beibehalten.
- [ ] Automatische Teilchecks sind formative Rueckmeldung; erst eine explizite Abschlussregel darf den Aufgabenstatus auf `passed` oder `failed` setzen.

Abnahme:
- [ ] Gleiche Modellstruktur ergibt reproduzierbare Checks; Studentennamen und Anordnungsreihenfolge allein veraendern das Ergebnis nicht.
- [ ] Jeder Fehler ist einer Regel und einem sichtbaren Element zugeordnet und mit einer lernfoerdernden Erklaerung versehen.
- [ ] Kein Feedback und keine Student-Antwort enthaelt Soll-Namen oder die verdeckte Referenzloesung.

Gate C -> D:
- [ ] DB-Ende-zu-Ende (Admin + Student + Bewertung) abgeschlossen.

---

## Phase D: DB-Haertung

### D1. Tests
- [ ] Checker-Tests fuer Umbenennungen, Reihenfolge, fehlende/zusaetzliche Elemente, Typvarianten, PK/FK und falsche Kardinalitaeten.
- [ ] Negativtests fuer Feedback-Leaks (Soll-Namen, interne Rubrik-IDs und Loesungsmodell).
- [ ] Tests fuer mehrdeutige Zuordnung und manuellen Review-Fallback.
- [ ] API-Tests fuer Draft, Check, Abgabe und Statusfluss.
- [ ] UI-Smoke fuer DB-Projektmodus und DB-Aufgabenmodus; sicherstellen, dass ihre Speicherungen getrennt bleiben.
- [ ] Regressionsliste fuer bekannte Kantenfaelle.

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
- [ ] Vorhandenen Aufgaben-Typ `uml` in Admin-Form, Import/Export und Testmodus durchgaengig authorbar machen.
- [ ] UML-Rubrik mit stabilen Rollen und internen IDs analog zum DB-Checker definieren; sichtbare Klassennamen bleiben tolerant.
- [ ] Aufgaben-Vorlagen fuer typische UML-Lernszenarien inklusive Beziehungen, Multiplizitaeten und Enum-Attributen ergaenzen.

### F2. Studenten-Renderer
- [x] UML-Canvas im Assignment vorhanden.
- [ ] Zwischenstand speichern und wiederherstellen Ende-zu-Ende absichern.
- [ ] Finale Abgabe und Statusfluss absichern.

### F3. Bewertung
- [ ] UML-Strukturchecker fuer Klassen, Attribute, Beziehungen, Rollen und Kardinalitaeten nach demselben Check-/Feedback-Vertrag umsetzen.
- [ ] Teilfeedback erklaert die fachliche Wirkung einer falschen Multiplizitaet, ohne Soll-Bezeichner offenzulegen.
- [ ] Mehrdeutige Zuordnung und manuelle Bewertung unterstuetzen.

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
1. A3 Bewertungsvertrag und Rubrikformat fuer DB/UML festlegen.
2. C1 Admin-Authoring so gestalten, dass Lehrkraefte fachliche Rollen und Toleranzen ohne JSON-Handarbeit definieren koennen.
3. C3 zunaechst mit der Aufgabe „UML-Klassendiagramm in relationales Schema umsetzen“ pilotieren.
4. D1 mit Umbenennungs-, Kardinalitaets-, Mehrdeutigkeits- und Feedback-Leak-Tests abschliessen.
5. Erst danach den UML-Projektmodus und den UML-Aufgaben-Checker als eigene Modi vervollstaendigen.
