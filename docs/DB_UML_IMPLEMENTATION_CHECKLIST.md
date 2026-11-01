# DB + UML Umsetzungscheckliste (Schrittweise)

Status: Arbeitsdokument, an den aktuellen Code- und Aufgabenstand angepasst
Strategie: In Stufe 1 einen fachlich sauberen UML- und DB-Designer fuer Projekte und Aufgaben bereitstellen; Aufgaben duerfen zunaechst manuell bewertet werden. Schon diese Stufe muss die spaetere lernorientierte automatische Pruefung architektonisch ermoeglichen. Erst Stufe 2 implementiert den Checker. Lokal entwickeln, danach isoliert in Beta erproben und erst nach Freigabe nach Live uebernehmen.

## Umsetzungsstand (2026-10-30)

- UML-Projekte koennen lokal ueber eine eigene Projektvorlage angelegt werden; der Projektmodus nutzt den bestehenden UML-Renderer und speichert das Modell getrennt als `uml_model.json`.
- Das UML-Modellformat Version 2 bewahrt stabile IDs fuer Klassen, Attribute und Beziehungen, Kardinalitaeten sowie die Kennzeichnung abgeleiteter Attribute.
- Projekt-Speicherung ist entprellt, meldet Fehler sichtbar und wird vor einem Wechsel zu einem anderen Projekt abgeschlossen.
- Projekt-Typmigration 066 wurde nach Verifikation der lokalen DB-Verbindung ausschliesslich lokal ausgefuehrt.
- Lokaler UI-Smoke-Test: UML-Projekt angelegt, Klassen und Attribute geaendert, gespeichert und nach erneutem Laden wiederhergestellt. Dabei wurde ein Projekt-Kontextfehler beim Rendern behoben; das temporaere Testprojekt wurde danach geloescht.
- Lokaler UI-Smoke-Test: Beziehung zwischen zwei Klassen angelegt, Bezeichner und Kardinalitaeten gepflegt, gespeichert und nach erneutem Laden wiederhergestellt; temporaeres Projekt geloescht.
- UML-Aufgaben koennen im Admin-Formular angelegt werden; manuelle Bewertung und Aufgabenbeschreibung werden fuer diesen Typ erzwungen. Der Aufgaben-Canvas zeigt die Aufgabenbeschreibung und meldet Speichern-Fehler sichtbar.
- Beziehungskardinalitaeten sind im UML-Inspector links und rechts ueber die Auswahl `1`, `0..1`, `1..*` und `0..*` setzbar und werden an den jeweiligen Beziehungsenden angezeigt. Bezeichner werden ebenfalls direkt an der Beziehung dargestellt. Beziehungen lassen sich durch Anklicken auswaehlen und einzeln loeschen; beim Loeschen einer Klasse bestaetigt eine Sicherheitsabfrage die Klasse und die mitgeloeschten Beziehungen.
- UML-Aufgaben koennen die einfachen Datentypen pro Aufgabe aktivieren; `String` erhaelt eine konfigurierbare Standardlaenge (30). Ein lokal positioniertes, editierbares Kombifeld bietet die Vorschlaege direkt neben dem Datentyp-Eingabefeld an. Die Einstellung wird als `uml_template.type_config` gespeichert, ohne eine Datenbankmigration zu benoetigen. Strukturen und frei definierte Datentypen bleiben vorerst ausserhalb des Umfangs.
- Aufgaben koennen optional mit einem Teilmodell starten. Es wird in `uml_template.starter_model` gespeichert und nur dann als Ausgangsmodell verwendet, wenn noch kein Lernenden-Entwurf gespeichert ist. Im Admin-Formular kann es als JSON bearbeitet werden.
- Neue UML-Klassen werden auf freien Rasterplaetzen angeordnet. Klassen lassen sich am Titel ziehen; Canvas-Position und Modell bleiben dabei synchron. Attribute werden einzeln im Diagramm ausgewaehlt und koennen mit Entf oder „Loeschen“ entfernt werden; Doppelklick oeffnet die Bearbeitung. Datentypen koennen als `name:Datentyp` direkt in der Attributzeile oder separat im Inspector eingetragen werden; eigene Datentypen sind zulaessig. Im Assoziationsmodus waehlt ein Klick weiterhin nur Quelle oder Ziel aus, ohne die Klasse zu verschieben; Escape bricht den Modus ab.
- Bei DB-/UML-Aufgaben ist `Aufgabe (Student-facing)` der knappe Gesamtauftrag; der ausfuehrliche Haupttext gehoert in `Description` und wird Studierenden links unter `Details` angezeigt. Die Admin-Formulare weisen typabhaengig darauf hin.
- Das lokale Test-Assignment 47 verwendet diese Aufteilung fuer beide Online-Shop-Beispielaufgaben; Anzeige und Aufgabenwechsel wurden im lokalen Testmodus geprueft.
- Der UML-Zwischenspeicher im Admin-Testmodus bleibt lokal und schreibt nicht in `user_tasks`; ein Regressionstest prueft, dass dabei kein API-Aufruf erfolgt.
- Noch offen: UML-Aufgabenmodus Ende-zu-Ende, manuelle Bewertung in der Abgabeansicht und Beta-Datenbank-Isolation. Kein Beta-/Live-Deployment oder Beta-/Live-Datenbankzugriff wurde fuer diesen Schritt ausgefuehrt.

## 1) Leitlinien (fix vor Umsetzung)

- Task-Entsperren: Eine reine Anfrage `{id, locked}` muss unabhängig vom Aufgabentyp funktionieren, darf keine Inhalte oder Dateien ändern und bleibt bei gesperrtem Assignment verboten. Gemischte Lock-/Inhaltsänderungen werden abgewiesen. `node scripts/test_task_order.js` prüft diesen API-Pfad mit einer isolierten Testdatenbank für alle zehn Tasktypen; direktes SQL-Entsperren ist kein Ersatz für einen API-Regressionstest.

- Dashboard-Regression nach dem UML-Rollout: Der Assignment-Semesterfilter muss seine Einträge aus der Semester-Stammtabelle laden, „Alle“ und die gespeicherte Auswahl erhalten sowie Suche und Pagination kombinieren. Lokaler Test: `node scripts/test_assignment_semester_filter.js`. Beim Paketbau vorhandene Live-Funktionen mit dem gesicherten Vorstand abgleichen; reine Designer-Tests decken diese Oberfläche nicht ab.
- Dashboard-Abgleich (2026-10-31): Semesterfelder, Klonen mit Zielsemester, Assignment-/Task-Sperren und Delta-/Manifest-Bedienelemente wiederhergestellt. Vergleichsbasis: gesicherter lokaler Beta-Vorstand vor dem UML-Paket und frühere Manifest-Deploymentpakete; kein direkter Dateiexport des Live-Backups. Zusätzlicher Test: `node scripts/test_admin_dashboard_regression.js`. UML-Templates bleiben beim Bearbeiten erhalten; gesperrte Task-Inhalte bleiben geschützt, während die neue positionsbasierte Umsortierung weiterhin erlaubt ist. Nach ausdrücklicher Freigabe auf Live ausgerollt (Paket `admin-dashboard-regression-hotfix-20261031.zip`, zwei Dateien). Live geprüft: „Alle“ (20 Assignments), SS26 (15), WS2627 (5), Semesterfelder in Bearbeiten/Klonen, gesperrte Task-Bedienung und Delta-Dry-Run mit zwei unveränderten Dateien. Keine Aufgabendaten durch die Live-Tests geändert; Server-Dateibackup automatisch erstellt.

- Entkopplung pro Typ: `code`, `gui`, `db`, `uml` jeweils eigener Renderer/Controller.
- Keine Cross-Mode-Logik in Einzel-Features (kein verborgenes `if html|mixed` fuer DB/UML).
- Assignment-Typen bleiben explizit getrennt (`db_model`, `uml`).
- Jede Phase hat ein Go/No-Go Gate mit klaren Abnahmekriterien.

---

## 2) Reihenfolge (verbindlich)

**Stufe 1 – Designer und manueller Review**
1. Phase A: Datenmodell-/Designer-Vertrag und Systemgrenzen festlegen
2. Phase B: DB- und UML-Designer fuer Projektmodus fertigstellen
3. Phase C: DB- und UML-Designer in Aufgaben integrieren; Speichern, Wiedereroeffnen und manuelle Bewertung
4. Phase D: Lokal testen, danach isolierter Beta-Pilot mit Testkonten

**Stufe 2 – Lernorientierter Checker**
5. Phase E: Referenzrubrik und strukturelle Zuordnung ohne Namenspflichten
6. Phase F: Teilchecks, lernfoerderndes Feedback und manueller Fallback
7. Phase G: Regression, Beta-Pilot der Checks und gesonderte Live-Freigabe

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

### A3. Designer-Vertrag fuer spaetere automatische Checks
- [ ] UML- und DB-Modelle sind versionierte, validierte, serialisierbare Graphmodelle; sichtbare Namen sind editierbare Labels und keine technischen Identitaeten.
- [ ] Jedes Element und jede Beziehung besitzt eine stabile technische ID, die bei Umbenennung und Layoutaenderung erhalten bleibt.
- [ ] Beziehungen speichern Richtung/Endpunkte sowie Kardinalitaeten je Ende strukturiert, nicht nur als Freitext oder gezeichnete Linie.
- [ ] Modellieren und Pruefen sind getrennt: Projekte und studentische Antworten enthalten keine versteckten Referenzrollen oder Musterloesungsdaten.
- [ ] Ein spaeteres Bewertungsrubrik-Format kann erwartete fachliche Rollen, Beziehungen und Regeln getrennt und serverseitig geschuetzt ablegen.
- [ ] Der Designer kann zusaetzliche fachlich relevante Eigenschaften strukturiert ausdruecken (z. B. PK/FK, Datentyp, Enum, abgeleitetes Attribut), ohne dass eine bestimmte Bewertung bereits implementiert sein muss.
- [ ] Export/Import und Renderer bewahren unbekannte zukunftige Felder oder schlagen bei nicht unterstuetzten Schema-Versionen explizit fehl; keine stille Datenvernichtung.

Abnahmekriterien:
- [ ] Umbenennen eines Elements veraendert seine technische Identitaet oder Verbindungen nicht.
- [ ] Speichern/Laden erhaelt Knoten, Attribute, Beziehungen, Kardinalitaeten und Layout.
- [ ] Die Modell-API fuer Studierende gibt keine Rubrik oder Referenzloesung aus.
- [ ] Eine manuelle Bewertung ist ohne Checker moeglich; der Datenvertrag verhindert dessen spaetere Ergaenzung nicht.

### A4. Bewertungsvertrag fuer Stufe 2
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

## Phase B: Projekt-Designer in Stufe 1

### B1. Datenmodell und Persistenz
- [x] `db_model.json` Schema versionieren (inkl. Migrationstrategie)
- [x] Stabiler Serializer/Deserializer (Load/Save) implementiert
- [x] SQL-Export aus Modell deterministisch erzeugen
- [ ] DB-Modellformat auf stabile, von Namen getrennte IDs und verlustfreien Import/Export pruefen.
- [ ] UML-Modellformat fuer Projekte definieren und versionieren; Struktur und Layout getrennt halten.

Abnahme:
- [x] Modell laden/speichern ist verlustfrei.
- [x] SQL-Export ist reproduzierbar fuer identisches Modell.
- [ ] DB- und UML-Modell bleiben bei Umbenennung, Speichern, Laden und Export strukturell konsistent.

### B2. Editor-Funktionen
- [x] Tabellen: erstellen, umbenennen, loeschen
- [x] Spalten: Name, Datentyp, Pflicht, Default
- [x] Schluessel: PK/FK setzen/entfernen
- [ ] Beziehungen modellieren (mind. 1:n und n:m via Zwischentabelle)
- [ ] UML-Editor: Klassen, Attribute, Beziehungen und Kardinalitaeten erstellen, bearbeiten, verbinden und loeschen.
- [ ] Fachliche Daten (z. B. Kardinalitaet, PK/FK, Datentyp) duerfen nicht nur visuell notiert sein.

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

## Phase C: Aufgaben-Designer und manuelle Bewertung (Stufe 1)

### C1. Authoring (Admin)
- [ ] UML- und DB-Aufgabentypen in Authoring, Import/Export, Testmodus und Aufgabenlisten durchgaengig anbieten.
- [ ] UML-zu-DB-Aufgabe kann ein UML-Quellmodell und eine DB-Modellantwort getrennt speichern und darstellen.
- [ ] Referenzloesung darf fuer spaetere Checks getrennt vom studentischen Inhalt abgelegt werden, ist in Stufe 1 aber nicht fuer Studierende abrufbar.
- [ ] Manuellen Bewertungsweg fuer Lehrkraefte klar und vollstaendig unterstuetzen.

Abnahme:
- [ ] Lehrperson kann die Aufgabenstellung und ein Quellmodell ohne JSON-Handarbeit erstellen und die studentische Abgabe manuell bewerten.

### C2. Studentenansicht
- [ ] Vorhandenen `db_model`-Canvas fuer Tabellen, Felder, Datentypen, PK und FK als Antwort auf ein UML-Quellmodell einsetzen.
- [ ] UML-Canvas kann im Aufgabenmodus Klassendiagramme als Antwort erstellen.
- [ ] Studenten duerfen fachlich passende eigene Namen fuer Tabellen und Felder waehlen; Soll-Namen werden nicht als UI-Vorgabe eingeblendet.
- [ ] Zwischenstand und erneutes Oeffnen pruefen; Abgabe bleibt bis zur Bewertung als `submitted` review-faehig.
- [ ] Aufgabenmodus darf DB-Projektdateien und Projekt-SQL nicht veraendern.
- [ ] Quellmodell und Antwortmodell sind fuer Studierende eindeutig unterscheidbar.

Abnahme:
- [ ] Studierende koennen ein relationales Schema erstellen, speichern, erneut oeffnen und abgeben.

### C3. Manuelle Bewertung
- [ ] Lehrkraft kann Modellabgabe wieder oeffnen, lesen und Aufgabenstatus/Bewertung setzen.
- [ ] Student sieht nach Abgabe einen klaren Status; die Abgabe wird nicht automatisch als fachlich korrekt markiert.
- [ ] Bewertung und Feedback koennen ohne automatische Rubrikpruefung gespeichert werden.

Abnahme:
- [ ] Designer-Aufgabe funktioniert Ende-zu-Ende mit manueller Bewertung, unabhaengig vom spaeteren Checker.

---

## Phase E: Rubrik und Checker-Kern (Stufe 2)

### E1. Authoring und Rubrik
- [ ] `db_model`-Rubrik fuer eine UML-zu-relationales-Schema-Aufgabe ohne JSON-Handarbeit definieren.
- [ ] Rubrikeditor von der studentischen Loesungsansicht trennen; erwartete Elemente und Rollen duerfen nicht in Feedback oder Student-API-Daten landen.
- [ ] Pro Rubrikregel festlegen, ob sie strikt, tolerant oder manuell zu bewerten ist. Dazu gehoeren alternative Schluessel, Datentypfamilien und berechnete/gespeicherte Attribute.
- [ ] Import/Export von Aufgaben samt Rubrik validieren und gegen ungewollte Offenlegung absichern.

### E2. Strukturelle Bewertung
- [ ] Soll- und Ist-Modell anhand interner fachlicher Rollen statt exakter sichtbarer Namen abgleichen.
- [ ] Zuordnung bei Umbenennungen deterministisch pruefen: Graphstruktur, Attribute/Feldrollen, Datentypen und Schluesselbeziehungen; Ambiguitaet explizit erkennen.
- [ ] Beziehungen, PK/FK, Kardinalitaeten und Regelverletzungen einzeln pruefen.
- [ ] Abgeleitete Werte wie Positions-/Gesamtsummen gemaess Rubrik als berechnet oder explizit gespeichert akzeptieren.

Abnahme:
- [ ] Gleiche Modellstruktur ergibt reproduzierbare Checks; Studentennamen und Anordnungsreihenfolge allein veraendern das Ergebnis nicht.
- [ ] Checker liefert strukturierte Resultate pro Rubrikregel und markiert mehrdeutige Zuordnungen explizit.

## Phase F: Lernfeedback und Bewertungsfluss (Stufe 2)

### F1. Rueckmeldung
- [ ] Teilfeedback erklaert fachliche Fehler, etwa eine falsche Kardinalitaet auf der konkreten Seite der studentischen Beziehung, ohne Soll-Namen zu verraten.
- [ ] Jeder Fehler ist einer Regel und einem sichtbaren Element zugeordnet und mit einer lernfoerdernden Erklaerung versehen.
- [ ] Feedback und Student-API enthalten keine Soll-Namen, Rubrik-IDs oder verdeckte Referenzloesung.
- [ ] Manuellen Review-Fallback bei Mehrdeutigkeit oder fachlich vertretbarer, nicht hinterlegter Alternative beibehalten.

### F2. Status und Bewertung
- [ ] Automatische Teilchecks sind formative Rueckmeldung; erst eine explizite Abschlussregel darf den Aufgabenstatus auf `passed` oder `failed` setzen.
- [ ] Lehrkraft kann automatische Ergebnisse nachvollziehen und bei Bedarf manuell uebersteuern.

Abnahme:
- [ ] Feedback ist verstaendlich, reproduzierbar und verrät keine Loesungsnamen.
- [ ] Mehrdeutige oder nicht hinterlegte fachliche Alternativen koennen manuell bewertet werden.

## Rollout-Gates fuer beide Stufen

### D1. Tests
- [ ] Stufe 1: Schema-, Renderer-, Speichern/Laden-, Projektmodus- und manuelle Aufgabenreview-Tests lokal erfolgreich.
- [ ] Stufe 2: Checker-Tests fuer Umbenennungen, Reihenfolge, fehlende/zusaetzliche Elemente, Typvarianten, PK/FK und falsche Kardinalitaeten.
- [ ] Stufe 2: Negativtests fuer Feedback-Leaks (Soll-Namen, interne Rubrik-IDs und Loesungsmodell) sowie Mehrdeutigkeit.
- [ ] API-Tests fuer Draft, Abgabe, Review und – in Stufe 2 – Check/Statusfluss.
- [ ] UI-Smoke fuer DB-Projektmodus und DB-Aufgabenmodus; sicherstellen, dass ihre Speicherungen getrennt bleiben.
- [ ] UML-Projekt- und Aufgabenmodus auf demselben Release-Kandidaten pruefen.
- [ ] Regressionsliste fuer bekannte Kantenfaelle.

Gate Stufe 1:
- [ ] Stufe-1-Designer ist lokal und in Beta mit isolierten Testkonten akzeptiert; Live-Rollout ist ausdruecklich freigegeben.

Gate Checker:
- [ ] Checker und Feedback bestehen lokale Struktur-, Feedback-Leak- und Statusfluss-Tests.

### D2. Betrieb
- [ ] Performance-Smoketest (groessere Modelle)
- [ ] Fehlerprotokollierung fuer DB-Aktionen
- [ ] Rollback-Plan fuer kritische Releases

Abnahme:
- [ ] Lokal: automatisierte Tests plus dokumentierter manueller Smoke-Test bestanden.
- [ ] Beta: vor Testdatenanlage Datenbankziel und Kontentrennung verifiziert; dedizierte Beta-Testkonten und Testaufgaben/-projekte verwenden.
- [ ] Beta: Testkonten duerfen keine produktiven Assignments, Bewertungen oder Studentendaten veraendern; bei gemeinsamer Datenbank mit Live kein schreibender Beta-Test.
- [ ] Beta: Testaufgaben nur den Beta-Testkonten zuweisen; keine produktiven Kurse, Aufgabenlisten oder Lernenden mit Testinhalten veraendern.
- [ ] Beta: Delta-Paket zuerst im Dry-Run pruefen und nur auf Beta anwenden; die Beta-Instanz darf keine Live-Pakete anwenden.
- [ ] Live: Promotion erst nach dokumentierter fachlicher Freigabe aus Beta, Backup-/Rollback-Pruefung und separatem Deployment-Fenster.
- [ ] Migrationen und Testdaten sind getrennt zu planen; keine Testdaten oder Testkonten nach Live uebernehmen.

Gate G:
- [ ] Stufe-2-Checker ist separat lokal und in Beta mit isolierten Testkonten akzeptiert; Live-Rollout ist ausdruecklich freigegeben.

---

### B4. UML-Projekteditor grafisch (MVP)

UML-Scope:
- [ ] Klassenname, Attribute, Beziehungen, Kardinalitaeten
- [ ] Vererbung optional als zweite Stufe
- [ ] Notation vereinheitlichen (MVP-Regeln)

Persistenz:
- [x] `uml_model.json` als eigenes Schema (keine Vermischung mit DB)
- [x] Versionierung/Validierung analog DB

Editor:
- [ ] Klasse erstellen/verbinden/loeschen
- [x] Kardinalitaet links und rechts ueber eine feste Auswahl pflegen und an den Beziehungsenden anzeigen.
- [ ] Grundlayout und Lesbarkeit

Abnahme:
- [x] UML-Klassen und Attribute koennen erstellt, gespeichert und geladen werden.

### C4. UML-Aufgaben (Stufe 1)

Authoring:
- [x] Vorhandenen Aufgaben-Typ `uml` im Admin-Form authorbar machen und manuelle Bewertung erzwingen.
- [ ] Import/Export und Testmodus fuer UML-Aufgaben Ende-zu-Ende pruefen.
- [ ] Aufgaben-Vorlagen fuer typische UML-Lernszenarien inklusive Beziehungen, Multiplizitaeten und Enum-Attributen ergaenzen.

Studenten-Renderer:
- [x] UML-Canvas im Assignment vorhanden.
- [x] Aufgabenbeschreibung im UML-Aufgaben-Canvas anzeigen.
- [x] UML-Zwischenspeichern im Admin-Testmodus gegen produktive Antwortspeicherung absichern.
- [ ] Zwischenstand speichern und wiederherstellen Ende-zu-Ende absichern.
- [ ] Finale Abgabe und Statusfluss absichern.

Bewertung:
- [ ] UML-Aufgaben werden in Stufe 1 manuell bewertet.

Abnahme:
- [ ] UML-Aufgabe funktioniert Ende-zu-Ende.

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

## 5) Konkretes Vorgehen und naechster Schritt

1. A3 als technischen Designer-Vertrag festlegen, ohne bereits den Checker umzusetzen.
2. B: DB- und UML-Modellformate sowie Projekt-Designer vervollstaendigen. IDs, Beziehungen, Kardinalitaeten und fachliche Eigenschaften muessen strukturiert und verlustfrei sein.
3. C: Aufgabenmodus mit Quellmodell und Antwortmodell integrieren. Erstes Beispiel ist „UML-Klassendiagramm in relationales Schema umsetzen“; Abgabe wird manuell bewertet.
4. D lokal: isolierte Fixtures/Testkonten verwenden, die Kernflows und Speicherung pruefen; keine Live-Aufgaben oder produktiven Nutzerdaten fuer Tests veraendern.
5. D Beta: vor dem ersten schreibenden Test Beta-DB-Verbindung, Login-URL und Datenbestand verifizieren. Beta kann ausgewaehlte Live-Zustaende enthalten. Nur dedizierte Beta-Testkonten und eigens angelegte Testaufgaben/-projekte nutzen. Falls Beta und Live dieselbe schreibbare Datenbank verwenden, Beta nicht fuer schreibende Tests freigeben, bis Isolation hergestellt ist.
6. Erst nach Beta-Abnahme und expliziter Freigabe den Stufe-1-Code nach Live promoten. Testkonten, Testaufgaben und Testdaten bleiben ausserhalb von Live.
7. E/A bis G: Rubrik, Checker und Feedback separat entwickeln, lokal und anschliessend Beta testen. Erst nach eigener Abnahme in Live aktivieren.

Go/No-Go fuer den unmittelbaren Start:
- [ ] Modell-/Designer-Vertrag A3 ist abgestimmt.
- [ ] Es ist geklaert, wie Antwortmodelle und Aufgaben-Quellmodelle ohne Kopplung an Projektpersistenz gespeichert werden.
- [ ] Beta-Datenbank-Isolation und Verfuegbarkeit dedizierter Testkonten sind vor Beta-Schreibtests nachgewiesen.
