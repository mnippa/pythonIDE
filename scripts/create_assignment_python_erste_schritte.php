<?php
/**
 * Create or refresh the absolute-beginner onboarding assignment.
 *
 * The script updates tasks by position so rerunning it does not create duplicates.
 */

require_once __DIR__ . '/../config/database.php';

const ASSIGNMENT_TITLE = 'Erste Schritte mit Python und der Lernumgebung';
const ASSIGNMENT_DESCRIPTION = 'Ein Einstieg ohne Vorkenntnisse: erste Python-Befehle schreiben und dabei Run, Check, Feedback, Hinweise, Context-Hilfe und Abgabe der Lernumgebung kennenlernen.';

function tests(array $testCases): string
{
    return json_encode($testCases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function task(
    string $title,
    string $taskText,
    string $description,
    string $stoff,
    string $codeTemplate,
    string $solutionCode,
    array $testCases,
    string $hint1,
    string $hint2,
    string $hint3
): array {
    return [
        'title' => $title,
        'task_text' => $taskText,
        'description' => str_replace('\n', "\n", $description),
        'stoff' => $stoff,
        'code_template' => $codeTemplate,
        'solution_code' => $solutionCode,
        'test_cases' => tests($testCases),
        'hint1' => $hint1,
        'hint2' => $hint2,
        'hint3' => $hint3,
    ];
}

function getAdminUserId(PDO $pdo): int
{
    $adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
    if ($adminId === false) {
        throw new RuntimeException('Kein Admin-Benutzer fuer created_by gefunden.');
    }

    return (int)$adminId;
}

function getOrCreateAssignment(PDO $pdo): int
{
    $select = $pdo->prepare('SELECT id FROM assignments WHERE title = :title LIMIT 1');
    $select->execute(['title' => ASSIGNMENT_TITLE]);
    $assignmentId = $select->fetchColumn();

    if ($assignmentId !== false) {
        $update = $pdo->prepare(
            'UPDATE assignments
             SET description = :description, difficulty = "beginner", is_active = 1, updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute([
            'description' => ASSIGNMENT_DESCRIPTION,
            'id' => (int)$assignmentId,
        ]);
        return (int)$assignmentId;
    }

    $insert = $pdo->prepare(
        'INSERT INTO assignments (title, description, created_by, is_active, difficulty, created_at, updated_at)
         VALUES (:title, :description, :created_by, 1, "beginner", NOW(), NOW())'
    );
    $insert->execute([
        'title' => ASSIGNMENT_TITLE,
        'description' => ASSIGNMENT_DESCRIPTION,
        'created_by' => getAdminUserId($pdo),
    ]);

    return (int)$pdo->lastInsertId();
}

function saveTask(PDO $pdo, int $assignmentId, int $position, array $taskData): int
{
    $select = $pdo->prepare(
        'SELECT id FROM tasks WHERE assignment_id = :assignment_id AND position = :position ORDER BY id'
    );
    $select->execute([
        'assignment_id' => $assignmentId,
        'position' => $position,
    ]);
    $existingIds = $select->fetchAll(PDO::FETCH_COLUMN);

    if (count($existingIds) > 1) {
        throw new RuntimeException("Mehrere Aufgaben an Position {$position} gefunden.");
    }

    $values = [
        'assignment_id' => $assignmentId,
        'position' => $position,
        'title' => $taskData['title'],
        'description' => $taskData['description'],
        'task_text' => $taskData['task_text'],
        'code_template' => $taskData['code_template'],
        'hint1' => $taskData['hint1'],
        'hint2' => $taskData['hint2'],
        'hint3' => $taskData['hint3'],
        'stoff' => $taskData['stoff'],
        'test_cases' => $taskData['test_cases'],
        'solution_code' => $taskData['solution_code'],
    ];

    if ($existingIds) {
        $values['id'] = (int)$existingIds[0];
        unset($values['assignment_id']);
        $update = $pdo->prepare(
            'UPDATE tasks SET
                title = :title,
                description = :description,
                task_text = :task_text,
                position = :position,
                task_type = "code",
                task_difficulty = "basic",
                problem_type = "code_completion",
                folderstructure = 0,
                allowDownload = 0,
                allow_code_ui_web_edit = 0,
                code_template = :code_template,
                hint1 = :hint1,
                hint2 = :hint2,
                hint3 = :hint3,
                stoff = :stoff,
                expected_output = "",
                test_cases = :test_cases,
                solution_code = :solution_code,
                max_attempts = 8,
                iterations_count = 1,
                show_solution = 1,
                show_solution_code = 1,
                min_keywords_required = 0,
                manual_review_required = 0,
                updated_at = NOW()
             WHERE id = :id'
        );
        $update->execute($values);
        return (int)$existingIds[0];
    }

    $insert = $pdo->prepare(
        'INSERT INTO tasks (
            assignment_id, title, description, task_text, position,
            task_type, task_difficulty, problem_type, folderstructure,
            allowDownload, allow_code_ui_web_edit, code_template,
            hint1, hint2, hint3, stoff, expected_output, test_cases,
            solution_code, max_attempts, iterations_count, show_solution,
            show_solution_code, min_keywords_required, manual_review_required,
            created_at, updated_at
        ) VALUES (
            :assignment_id, :title, :description, :task_text, :position,
            "code", "basic", "code_completion", 0,
            0, 0, :code_template,
            :hint1, :hint2, :hint3, :stoff, "", :test_cases,
            :solution_code, 8, 1, 1,
            1, 0, 0,
            NOW(), NOW()
        )'
    );
    $insert->execute($values);

    return (int)$pdo->lastInsertId();
}

$tasks = [
    task(
        'Das erste Programm ausfuehren',
        'Fuehre den vorbereiteten Code mit "Run" aus.',
        'Veraendere den Code noch nicht. Klicke auf "Run" und beobachte rechts die Ausgabe. Danach prueft "Check", ob genau der erwartete Text ausgegeben wird.\n\nAutomatische Pruefung: Die Ausgabe muss Hallo Python! lauten.',
        '<p><code>print(...)</code> zeigt Text oder Werte in der Ausgabe an. Text steht in Anfuehrungszeichen.</p>',
        "print(\"Hallo Python!\")\n# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "print(\"Hallo Python!\")",
        [['type' => 'output', 'expected' => ['Hallo Python!']]],
        'Klicke oben auf "Ausfuehren". Rechts erscheint die Ausgabe.',
        'Mit "Pruefen" wird dein Programm automatisch mit der Aufgabe verglichen.',
        'Der vorbereitete Befehl print("Hallo Python!") ist bereits vollstaendig.'
    ),
    task(
        'Eine Ausgabe veraendern',
        'Gib den Text Ich lerne Python! aus.',
        'Ersetze AUSGABE in der vorbereiteten print-Zeile durch den geforderten Text in Anfuehrungszeichen. Entferne am Zeilenanfang das Zeichen #, damit Python die Zeile ausfuehrt.\n\nAutomatische Pruefung: Die Ausgabe muss Ich lerne Python! lauten.',
        '<p>Ein <code>#</code> beginnt einen Kommentar. Kommentare werden nicht ausgefuehrt. Ein Textwert wie <code>"Hallo"</code> wird String genannt.</p>',
        "# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "print(\"Ich lerne Python!\")",
        [['type' => 'output', 'expected' => ['Ich lerne Python!']]],
        'Entferne zuerst das # vor print.',
        'Setze den Text in Anfuehrungszeichen: "Ich lerne Python!"',
        'Die vollstaendige Zeile lautet: print("Ich lerne Python!")'
    ),
    task(
        'Eine Variable anlegen',
        'Speichere den Text Python in der Variablen sprache.',
        'Eine Variable erhaelt mit = einen Wert. Lege sprache an und gib die Variable danach aus.\n\nAutomatische Pruefung: sprache muss den Text Python enthalten.',
        '<p>Variablen sind Namen fuer gespeicherte Werte. Bei <code>sprache = "Python"</code> steht links der Name und rechts der Wert.</p>',
        "sprache = \"\"\n# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "sprache = \"Python\"\nprint(sprache)",
        [['type' => 'variable', 'expected_vars' => ['sprache' => 'Python']]],
        'Schreibe den Text Python zwischen die vorhandenen Anfuehrungszeichen.',
        'Gib eine Variable ohne Anfuehrungszeichen aus: print(sprache)',
        "sprache = \"Python\"\nprint(sprache)"
    ),
    task(
        'Zahlen addieren',
        'Berechne 7 + 5 und speichere das Ergebnis in summe.',
        'Ersetze die 0 durch die Rechnung. Gib summe anschliessend aus und nutze "Check", um das Ergebnis kontrollieren zu lassen.\n\nAutomatische Pruefung: summe muss die ganze Zahl 12 enthalten.',
        '<p>Zahlen brauchen keine Anfuehrungszeichen. Python berechnet Ausdruecke wie <code>7 + 5</code>, bevor der Wert gespeichert wird.</p>',
        "summe = 0\n# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "summe = 7 + 5\nprint(summe)",
        [['type' => 'variable', 'expected_vars' => ['summe' => 12]]],
        'Schreibe die Rechnung rechts neben das Gleichheitszeichen.',
        'Die Zuweisung beginnt mit summe =',
        "summe = 7 + 5\nprint(summe)"
    ),
    task(
        'Einen Fehler mit Feedback beheben',
        'Pruefe den fehlerhaften Code und korrigiere ihn.',
        'Klicke zuerst auf "Check" und lies die Fehlermeldung. Repariere danach die unvollstaendige Textzeile und pruefe erneut. Fehler sind ein normaler Teil des Programmierens.\n\nAutomatische Pruefung: gruss muss Hallo! enthalten und ausgegeben werden.',
        '<p>Eine Fehlermeldung nennt meist die betroffene Zeile. Bei Text muessen das oeffnende und das schliessende Anfuehrungszeichen vorhanden sein.</p>',
        "gruss = \"Hallo!\n# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "gruss = \"Hallo!\"\nprint(gruss)",
        [
            ['type' => 'variable', 'expected_vars' => ['gruss' => 'Hallo!']],
            ['type' => 'output', 'expected' => ['Hallo!']],
        ],
        'Lies beim ersten Check, welche Zeile Python nennt.',
        'Am Ende von Hallo! fehlt ein schliessendes Anfuehrungszeichen.',
        "gruss = \"Hallo!\"\nprint(gruss)"
    ),
    task(
        'Context-Hilfe verwenden',
        'Nutze die Context-Hilfe, um text in Grossbuchstaben zu speichern.',
        'Setze den Cursor hinter <code>text.</code> und tippe bei Bedarf erneut einen Punkt. Waehle in der Vorschlagsliste <code>upper</code>; die Context-Hilfe am Editor erklaert die Methode. Speichere das Ergebnis in gross. Nutze erst danach die aufklappbaren Hinweise, falls du noch Hilfe brauchst.\n\nAutomatische Pruefung: gross muss PYTHON enthalten und die Methode upper() muss verwendet werden.',
        '<p>Nach einem Punkt bietet der Editor passende Methoden an. Die Context-Hilfe zeigt Informationen zur markierten Methode. <code>upper()</code> erzeugt einen neuen Text in Grossbuchstaben.</p>',
        "text = \"Python\"\ngross = text.\n# print(AUSGABE)  # Bitte eigenstaendig ausfuellen",
        "text = \"Python\"\ngross = text.upper()\nprint(gross)",
        [
            ['type' => 'variable', 'expected_vars' => ['gross' => 'PYTHON']],
            ['type' => 'code_check', 'keywords' => ['\\.upper\\s*\\('], 'operator' => 'AND', 'feedback' => 'Nutze die in der Context-Hilfe gezeigte Methode upper().'],
        ],
        'Markiere in der Vorschlagsliste upper, um die Context-Hilfe zu sehen.',
        'Eine Methode wird mit Klammern aufgerufen: upper()',
        "gross = text.upper()\nprint(gross)"
    ),
    task(
        'Mehrere Rechenarten',
        'Berechne Summe, Differenz, Produkt und Quotient von 12 und 4.',
        'Speichere die vier Ergebnisse in summe, differenz, produkt und quotient. Verwende +, -, * und /.\n\nAutomatische Pruefung: Erwartet werden 16, 8, 48 und 3.0 in den genannten Variablen.',
        '<p>Python verwendet <code>+</code> fuer Addition, <code>-</code> fuer Subtraktion, <code>*</code> fuer Multiplikation und <code>/</code> fuer Division.</p>',
        "zahl1 = 12\nzahl2 = 4\n\nsumme = 0\ndifferenz = 0\nprodukt = 0\nquotient = 0\n\n#Hier Ausgabe",
        "zahl1 = 12\nzahl2 = 4\n\nsumme = zahl1 + zahl2\ndifferenz = zahl1 - zahl2\nprodukt = zahl1 * zahl2\nquotient = zahl1 / zahl2\n\nprint(summe, differenz, produkt, quotient)",
        [['type' => 'variable', 'expected_vars' => ['summe' => 16, 'differenz' => 8, 'produkt' => 48, 'quotient' => 3.0]]],
        'Verwende fuer jedes Ergebnis eine eigene Zuweisung.',
        'Beispiel fuer die Addition: summe = zahl1 + zahl2',
        'Setze entsprechend -, * und / in den drei weiteren Zuweisungen ein.'
    ),
    task(
        'Text und Zahl unterscheiden',
        'Bestimme die Typnamen von "12" und 12.',
        'Speichere die Typnamen mit <code>type(...).__name__</code> in typ_text und typ_zahl. Beachte: Anfuehrungszeichen machen aus einer Zahl einen Text.\n\nAutomatische Pruefung: typ_text muss str und typ_zahl muss int enthalten.',
        '<p><code>str</code> bezeichnet Text, <code>int</code> eine ganze Zahl. <code>type(wert).__name__</code> liefert den Typnamen als Text.</p>',
        "zahl_als_text = \"12\"\nzahl = 12\n\ntyp_text = \"\"\ntyp_zahl = \"\"\n\n#Hier Ausgabe",
        "zahl_als_text = \"12\"\nzahl = 12\n\ntyp_text = type(zahl_als_text).__name__\ntyp_zahl = type(zahl).__name__\n\nprint(typ_text, typ_zahl)",
        [['type' => 'variable', 'expected_vars' => ['typ_text' => 'str', 'typ_zahl' => 'int']]],
        'Wende type(...).__name__ zuerst auf zahl_als_text an.',
        'Fuer die zweite Variable verwendest du type(zahl).__name__.',
        "typ_text = type(zahl_als_text).__name__\ntyp_zahl = type(zahl).__name__"
    ),
    task(
        'Text in eine Zahl umwandeln',
        'Wandle punkte_text in eine ganze Zahl um und addiere 5.',
        'Nutze <code>int(...)</code>. Speichere die umgewandelte Zahl in punkte und das Ergebnis in gesamt.\n\nAutomatische Pruefung: punkte muss 17 und gesamt muss 22 enthalten.',
        '<p><code>int("17")</code> wandelt passenden Text in eine ganze Zahl um. Erst danach kann Python die Zahl 5 addieren.</p>',
        "punkte_text = \"17\"\npunkte = 0\ngesamt = 0\n\n#Hier Ausgabe",
        "punkte_text = \"17\"\npunkte = int(punkte_text)\ngesamt = punkte + 5\n\nprint(gesamt)",
        [['type' => 'variable', 'expected_vars' => ['punkte' => 17, 'gesamt' => 22]]],
        'Beginne mit punkte = int(punkte_text).',
        'Addiere danach 5 zur Variablen punkte.',
        "punkte = int(punkte_text)\ngesamt = punkte + 5"
    ),
    task(
        'Eine Liste erstellen',
        'Speichere Rot, Gruen und Blau in der Liste farben.',
        'Eine Liste steht in eckigen Klammern. Trenne ihre Textwerte mit Kommas und setze jeden Text in Anfuehrungszeichen.\n\nAutomatische Pruefung: farben muss genau die drei Texte Rot, Gruen und Blau in dieser Reihenfolge enthalten.',
        '<p>Listen speichern mehrere Werte in einer festen Reihenfolge, zum Beispiel <code>["A", "B"]</code>.</p>',
        "farben = []\n\n#Hier Ausgabe",
        "farben = [\"Rot\", \"Gruen\", \"Blau\"]\n\nprint(farben)",
        [['type' => 'variable', 'expected_vars' => ['farben' => ['Rot', 'Gruen', 'Blau']]]],
        'Schreibe alle drei Werte zwischen [ und ].',
        'Jede Farbe ist Text und braucht eigene Anfuehrungszeichen.',
        'farben = ["Rot", "Gruen", "Blau"]'
    ),
    task(
        'Auf ein Listenelement zugreifen',
        'Speichere das erste Element aus farben in erste_farbe.',
        'Python zaehlt Listenpositionen ab 0. Greife mit eckigen Klammern auf das erste Element zu.\n\nAutomatische Pruefung: erste_farbe muss Rot enthalten.',
        '<p>Der Zugriff <code>liste[0]</code> liefert das erste Element, <code>liste[1]</code> das zweite.</p>',
        "farben = [\"Rot\", \"Gruen\", \"Blau\"]\nerste_farbe = \"\"\n\n#Hier Ausgabe",
        "farben = [\"Rot\", \"Gruen\", \"Blau\"]\nerste_farbe = farben[0]\n\nprint(erste_farbe)",
        [['type' => 'variable', 'expected_vars' => ['erste_farbe' => 'Rot']]],
        'Das erste Element hat den Index 0.',
        'Schreibe farben[0] rechts neben das Gleichheitszeichen.',
        'erste_farbe = farben[0]'
    ),
    task(
        'Abschlussaufgabe pruefen und abgeben',
        'Ergaenze das Programm, pruefe es und gib die Aufgabe ab.',
        'Wandle punkte_text mit int() um, addiere den bonus und speichere das Ergebnis in gesamt. Speichere ausserdem das erste Element aus sprachen in erste_sprache. Klicke auf "Check" und korrigiere moegliche Fehler. Wenn alle Checks erfolgreich sind, klicke auf "Abgeben".\n\nAutomatische Pruefung: punkte = 18, gesamt = 20 und erste_sprache = Python.',
        '<p>Diese Aufgabe verbindet Variablen, Umwandlung, Addition und Listenindex. "Check" gibt Rueckmeldung, ohne die Aufgabe endgueltig abzugeben. "Abgeben" schliesst sie ab.</p>',
        "name = \"Ada\"\npunkte_text = \"18\"\nbonus = 2\nsprachen = [\"Python\", \"JavaScript\"]\n\npunkte = 0\ngesamt = 0\nerste_sprache = \"\"\n\n#Hier Ausgabe",
        "name = \"Ada\"\npunkte_text = \"18\"\nbonus = 2\nsprachen = [\"Python\", \"JavaScript\"]\n\npunkte = int(punkte_text)\ngesamt = punkte + bonus\nerste_sprache = sprachen[0]\n\nprint(name, gesamt, erste_sprache)",
        [['type' => 'variable', 'expected_vars' => ['punkte' => 18, 'gesamt' => 20, 'erste_sprache' => 'Python']]],
        'Arbeite in drei Schritten: umwandeln, addieren, Listenelement auswaehlen.',
        'Nutze int(punkte_text), danach punkte + bonus und zuletzt sprachen[0].',
        "punkte = int(punkte_text)\ngesamt = punkte + bonus\nerste_sprache = sprachen[0]"
    ),
];

$pdo = getPdoConnection();

try {
    $pdo->beginTransaction();
    $assignmentId = getOrCreateAssignment($pdo);

    $taskIds = [];
    foreach ($tasks as $index => $taskData) {
        $taskIds[] = saveTask($pdo, $assignmentId, $index + 1, $taskData);
    }

    $count = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE assignment_id = :assignment_id');
    $count->execute(['assignment_id' => $assignmentId]);
    if ((int)$count->fetchColumn() !== count($tasks)) {
        throw new RuntimeException('Das Assignment enthaelt unerwartete zusaetzliche oder fehlende Aufgaben.');
    }

    $pdo->commit();

    echo "Assignment erstellt/aktualisiert: #{$assignmentId} | " . ASSIGNMENT_TITLE . PHP_EOL;
    echo 'Aufgaben: ' . count($taskIds) . PHP_EOL;
    echo 'Task-IDs: ' . implode(', ', $taskIds) . PHP_EOL;
    echo "OK" . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
