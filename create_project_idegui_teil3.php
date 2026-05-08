<?php
require_once __DIR__ . '/config/database.php';

$conn = getDbConnection();
$conn->begin_transaction();

try {
    $projectName = 'IDEGUI Teil 3 - Taschenrechner systematisch';

    $ownerStmt = $conn->prepare('SELECT user_id FROM projects WHERE id = 46 LIMIT 1');
    $ownerStmt->execute();
    $ownerRes = $ownerStmt->get_result();
    if ($ownerRes->num_rows === 0) {
        throw new Exception('Referenzprojekt 46 nicht gefunden.');
    }
    $ownerUserId = (int)$ownerRes->fetch_assoc()['user_id'];
    $ownerStmt->close();

    $findStmt = $conn->prepare('SELECT id FROM projects WHERE user_id = ? AND name = ? LIMIT 1');
    $findStmt->bind_param('is', $ownerUserId, $projectName);
    $findStmt->execute();
    $findRes = $findStmt->get_result();

    if ($findRes->num_rows > 0) {
        $projectId = (int)$findRes->fetch_assoc()['id'];
        echo "Projekt existiert bereits: ID $projectId\n";
    } else {
        $description = 'Teil 3: IDEGUI systematisch an einem Taschenrechner. Fokus auf data-element, ui.get/ui.set, linear und event-driven.';
        $code = '';
        $projectType = 'mixed';
        $visibility = 'private';
        $shareToken = null;

        $insertProject = $conn->prepare('INSERT INTO projects (user_id, name, description, code, project_type, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertProject->bind_param('issssss', $ownerUserId, $projectName, $description, $code, $projectType, $visibility, $shareToken);
        if (!$insertProject->execute()) {
            throw new Exception('Projekt konnte nicht erstellt werden: ' . $insertProject->error);
        }
        $projectId = (int)$conn->insert_id;
        $insertProject->close();
        echo "Projekt erstellt: ID $projectId\n";
    }
    $findStmt->close();

    function ensureFolder(mysqli $conn, int $projectId, ?int $parentFolderId, string $name): int {
        if ($parentFolderId === null) {
            $sel = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id IS NULL AND name = ? LIMIT 1');
            $sel->bind_param('is', $projectId, $name);
        } else {
            $sel = $conn->prepare('SELECT id FROM project_folders WHERE project_id = ? AND parent_folder_id = ? AND name = ? LIMIT 1');
            $sel->bind_param('iis', $projectId, $parentFolderId, $name);
        }
        $sel->execute();
        $res = $sel->get_result();
        if ($res->num_rows > 0) {
            $id = (int)$res->fetch_assoc()['id'];
            $sel->close();
            return $id;
        }
        $sel->close();

        $ins = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, ?, ?)');
        $ins->bind_param('iis', $projectId, $parentFolderId, $name);
        if (!$ins->execute()) {
            throw new Exception('Ordner konnte nicht erstellt werden: ' . $name . ' / ' . $ins->error);
        }
        $id = (int)$conn->insert_id;
        $ins->close();
        return $id;
    }

    function upsertFile(mysqli $conn, int $projectId, ?int $folderId, string $name, string $content, string $mimeType): void {
        if ($folderId === null) {
            $sel = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id IS NULL AND name = ? LIMIT 1');
            $sel->bind_param('is', $projectId, $name);
        } else {
            $sel = $conn->prepare('SELECT id FROM project_files WHERE project_id = ? AND folder_id = ? AND name = ? LIMIT 1');
            $sel->bind_param('iis', $projectId, $folderId, $name);
        }
        $sel->execute();
        $res = $sel->get_result();
        $fileSize = strlen($content);

        if ($res->num_rows > 0) {
            $fileId = (int)$res->fetch_assoc()['id'];
            $sel->close();
            $upd = $conn->prepare('UPDATE project_files SET content = ?, mime_type = ?, file_size = ?, updated_at = NOW() WHERE id = ?');
            $upd->bind_param('ssii', $content, $mimeType, $fileSize, $fileId);
            if (!$upd->execute()) {
                throw new Exception('Datei konnte nicht aktualisiert werden: ' . $name . ' / ' . $upd->error);
            }
            $upd->close();
            return;
        }
        $sel->close();

        $ins = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->bind_param('iisssi', $projectId, $folderId, $name, $content, $mimeType, $fileSize);
        if (!$ins->execute()) {
            throw new Exception('Datei konnte nicht erstellt werden: ' . $name . ' / ' . $ins->error);
        }
        $ins->close();
    }

    ensureFolder($conn, $projectId, null, 'includes');
    ensureFolder($conn, $projectId, null, 'img');

    $f01 = ensureFolder($conn, $projectId, null, '01_data_element_basis');
    $f02 = ensureFolder($conn, $projectId, null, '02_linearer_ablauf');
    $f03 = ensureFolder($conn, $projectId, null, '03_event_driven');

    $html = "<!doctype html>\n<html>\n<body>\n  <h1>Taschenrechner</h1>\n\n  <label>Zahl A</label>\n  <input data-element=\"zahl_a\" value=\"0\">\n\n  <label>Zahl B</label>\n  <input data-element=\"zahl_b\" value=\"0\">\n\n  <label>Operator (+, -, *, /)</label>\n  <input data-element=\"operator\" value=\"+\">\n\n  <button data-element=\"btn_berechnen\">Berechnen</button>\n\n  <p>Ergebnis: <span data-element=\"ergebnis\">-</span></p>\n  <p data-element=\"meldung\"></p>\n</body>\n</html>\n";

    $readme01 = "# 01 data-element Basis\n\nZiel:\n- HTML-Elemente ueber data-element benennen\n- Werte in Python lesen und setzen\n\nKernidee:\n- ui.get('name') liest\n- ui.set('name', 'wert') schreibt\n";

    $init01 = "import idegui as ui\n\n# Lesen mit Defaults\na_text = ui.get('zahl_a', '0')\nb_text = ui.get('zahl_b', '0')\nop = ui.get('operator', '+')\n\n# Nur anzeigen, damit das Mapping sichtbar wird\nui.set('meldung', 'Gelesen: A=' + str(a_text) + ' B=' + str(b_text) + ' OP=' + str(op))\nui.set('ergebnis', 'Noch keine Berechnung')\n";

    $readme02 = "# 02 Linearer Ablauf\n\nZiel:\n- Einmaliger Ablauf: get -> parsen -> rechnen -> set\n- Didaktischer Fokus auf Datenfluss\n";

    $init02 = "import idegui as ui\n\n\ndef parse_float(text, fallback=0.0):\n    try:\n        return float(str(text).replace(',', '.').strip())\n    except Exception:\n        return fallback\n\n\ndef berechne(a, b, op):\n    if op == '+':\n        return a + b\n    if op == '-':\n        return a - b\n    if op == '*':\n        return a * b\n    if op == '/':\n        if b == 0:\n            raise ValueError('Division durch 0 ist nicht erlaubt.')\n        return a / b\n    raise ValueError('Unbekannter Operator: ' + str(op))\n\n\ntext_a = ui.get('zahl_a', '0')\ntext_b = ui.get('zahl_b', '0')\nop = ui.get('operator', '+').strip()\n\na = parse_float(text_a, 0.0)\nb = parse_float(text_b, 0.0)\n\ntry:\n    result = berechne(a, b, op)\n    ui.set('ergebnis', str(result))\n    ui.set('meldung', 'Linear berechnet: fertig.')\nexcept Exception as ex:\n    ui.set('ergebnis', '-')\n    ui.set('meldung', 'Fehler: ' + str(ex))\n";

    $readme03 = "# 03 Event Driven\n\nZiel:\n- Reaktion auf Nutzerklick\n- Handler-Muster: lesen -> parsen -> verarbeiten -> set\n";

    $init03 = "import idegui as ui\n\n\ndef parse_float(text, fallback=0.0):\n    try:\n        return float(str(text).replace(',', '.').strip())\n    except Exception:\n        return fallback\n\n\ndef berechne(a, b, op):\n    if op == '+':\n        return a + b\n    if op == '-':\n        return a - b\n    if op == '*':\n        return a * b\n    if op == '/':\n        if b == 0:\n            raise ValueError('Division durch 0 ist nicht erlaubt.')\n        return a / b\n    raise ValueError('Unbekannter Operator: ' + str(op))\n\n\ndef berechnen_click(trigger):\n    text_a = ui.get('zahl_a', '0')\n    text_b = ui.get('zahl_b', '0')\n    op = ui.get('operator', '+').strip()\n\n    a = parse_float(text_a, 0.0)\n    b = parse_float(text_b, 0.0)\n\n    try:\n        result = berechne(a, b, op)\n        ui.set('ergebnis', str(result))\n        ui.set('meldung', 'Event verarbeitet (Button-Klick).')\n    except Exception as ex:\n        ui.set('ergebnis', '-')\n        ui.set('meldung', 'Fehler: ' + str(ex))\n\n\nui.on('btn_berechnen', 'click', berechnen_click)\nui.set('meldung', 'Bereit. Werte eingeben und auf Berechnen klicken.')\n";

    $rootReadme = "# IDEGUI Teil 3 - Taschenrechner systematisch\n\nOrdnerreihenfolge:\n1. 01_data_element_basis\n2. 02_linearer_ablauf\n3. 03_event_driven\n\nDidaktik:\n- Sehr wenig HTML-Fokus\n- Kernprinzip: Zugriff ueber data-element, ui.get und ui.set\n";

    $rootInit = "print('Teil 3 geladen. Oeffne 01, 02 oder 03 und starte dort init.py')\n";

    upsertFile($conn, $projectId, null, 'README.md', $rootReadme, 'text/markdown');
    upsertFile($conn, $projectId, null, 'init.py', $rootInit, 'text/x-python');

    upsertFile($conn, $projectId, $f01, 'index.html', $html, 'text/html');
    upsertFile($conn, $projectId, $f01, 'README.md', $readme01, 'text/markdown');
    upsertFile($conn, $projectId, $f01, 'init.py', $init01, 'text/x-python');

    upsertFile($conn, $projectId, $f02, 'index.html', $html, 'text/html');
    upsertFile($conn, $projectId, $f02, 'README.md', $readme02, 'text/markdown');
    upsertFile($conn, $projectId, $f02, 'init.py', $init02, 'text/x-python');

    upsertFile($conn, $projectId, $f03, 'index.html', $html, 'text/html');
    upsertFile($conn, $projectId, $f03, 'README.md', $readme03, 'text/markdown');
    upsertFile($conn, $projectId, $f03, 'init.py', $init03, 'text/x-python');

    $conn->commit();

    echo "Fertig. Projekt-ID: $projectId\n";
} catch (Throwable $e) {
    $conn->rollback();
    echo 'FEHLER: ' . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
