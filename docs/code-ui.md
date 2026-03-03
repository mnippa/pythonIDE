# Code_UI: Editierbares HTML-Template mit Reset

## Ziel

`code_ui` ist ein Task-Typ für Python + UI. Beim Anlegen wird automatisch eine Ordnerstruktur aktiviert und ein versioniertes UI-Template erzeugt.

## Automatisch erzeugte Template-Dateien

Bei `task_type = code_ui` werden (falls nicht vorhanden) in `storage/tasks/folders/task_<id>/` erzeugt:

- `index.html`
- `idegui.py`
- `code_ui.template.json`

Die Erzeugung passiert bei:
- Task-Create (`api/tasks/create.php`)
- Task-Update (`api/tasks/update.php`), wenn Typ auf `code_ui` gesetzt ist

## Versionierung

Die Template-Version ist aktuell: `1.0.0`.

Sie wird gespeichert in:
- `index.html` als Marker-Kommentar: `CODE_UI_TEMPLATE_VERSION`
- `idegui.py` als Marker-Kommentar: `CODE_UI_TEMPLATE_VERSION`
- `code_ui.template.json` als Feld `template_version`

Wichtig: Die Version bezieht sich auf die **automatisch erzeugte Vorlage**.

## Pflicht-Abschnitte in index.html

Damit `idegui` zuverlässig injizieren kann, müssen diese Container bestehen bleiben:

- `<div id="idegui-root" data-idegui-root="true"></div>`
- `<div id="idegui-output" data-idegui-output="true"></div>`

Zusätzlich sollte der Marker für die Template-Version im Kopf erhalten bleiben.

## Bearbeitung durch Admin und Schüler

`index.html` ist bewusst normal editierbar (gleiche Systematik wie andere Dateien):

- Admin/Testmodus: Bearbeitung über Folder-Tools (`api/tasks/folder-manage.php`)
- Schülermodus: Bearbeitung über User-Override (`api/user_tasks/folder-files.php`)

## Reset-Button (Schüler)

Im Dateibereich erscheint für `code_ui` im Schülermodus ein Button `♻️`.

Aktion:
- API: `POST /api/user_tasks/folder-files.php?action=reset_code_ui`
- Löscht User-Overrides der Task-Dateien (`user_task_files`)
- Setzt `init.py` wieder auf `tasks.code_template`

Damit lässt sich ein "zerschossener" Container schnell auf Template-Stand zurücksetzen.

## Hinweise

- `code_ui` erzwingt serverseitig `folderstructure = 1`.
- Falls kein `code_template` gesetzt ist, wird ein Default-Python-Template gesetzt.
- Für bestehende Datenbank-Installationen muss Migration `run_013.php` ausgeführt werden (Erweiterung `task_type` um `code_ui`).
