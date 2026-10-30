<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$tasks = [
    [
        'id' => 569,
        'title' => 'Dictionary: eine Artikelliste umstrukturieren',
        'task_text' => 'Übertrage die Angaben aus artikeldaten in das Dictionary artikel. Gib anschließend Bezeichnung und Bestandswert jeweils in einer eigenen Zeile aus.',
        'description' => '<p><code>artikeldaten</code> enthält genau einen Artikel: Bezeichnung, Stückpreis und Bestand, in dieser Reihenfolge.</p><p>Fasse die Angaben im Dictionary <code>artikel</code> mit selbst gewählten, aussagekräftigen Schlüsseln zusammen. Die Liste bleibt unverändert. Schleifen sind nicht erforderlich.</p><p>Ausgabe: zuerst die Bezeichnung, danach der Bestandswert, jeweils in einer eigenen Zeile ohne Beschriftung und aus dem Dictionary ermittelt. Der Code soll auch bei geänderten Listenwerten funktionieren.</p><p>Entwirf das Dictionary zunächst ohne Hinweis. Gleiche vor dem Check deine Schlüssel mit den im Hinweis genannten Prüfschlüsseln ab und passe gegebenenfalls ihre Benennung an. Der Check akzeptiert keine abweichenden Schlüssel.</p>',
        'hint1' => 'Für den automatischen Check muss artikel genau die Schlüssel bezeichnung, preis und bestand enthalten. Sie stehen für Artikelbezeichnung, Stückpreis und Stückzahl auf Lager. Die Reihenfolge der Einträge ist beliebig. Der Bestandswert ist Stückpreis × Bestand.',
        'init' => 'artikeldaten = ["Ordner", 3.5, 12]',
        'body' => "artikel = {\"bezeichnung\": artikeldaten[0], \"preis\": artikeldaten[1], \"bestand\": artikeldaten[2]}\nprint(artikel[\"bezeichnung\"])\nprint(artikel[\"preis\"] * artikel[\"bestand\"])",
        'inputs' => ['artikeldaten'],
        'outputs' => ['artikel', 'artikeldaten'],
        'randomizer' => "import random\nvalues = {\"artikeldaten\": [random.choice([\"Heft\", \"Mappe\", \"Stift\"]), random.randint(0, 100) / 4, random.randint(0, 40)]}",
        'cases' => [
            ['init_values' => [['Heft', 1.25, 0]], 'expected_values' => [['bezeichnung' => 'Heft', 'preis' => 1.25, 'bestand' => 0], ['Heft', 1.25, 0]]],
            ['init_values' => [['Stift', 0, 7]], 'expected_values' => [['bezeichnung' => 'Stift', 'preis' => 0, 'bestand' => 7], ['Stift', 0, 7]]],
        ],
        'pattern' => '^Ordner\\s*\\r?\\n\\s*42(?:\\.0+)?$',
    ],
    [
        'id' => 570,
        'title' => 'Dictionary: zwei Besprechungsräume beschreiben',
        'task_text' => 'Lege für die beiden beschriebenen Besprechungsräume jeweils ein passendes Dictionary an. Gib für jeden Raum seine Bezeichnung und die Anzahl der Plätze aus.',
        'description' => '<p>Der Besprechungsraum THD hat 80 Plätze und einen Beamer. Der Besprechungsraum T1.3.08 hat 20 Plätze und keinen Beamer.</p><p>Erfasse die Angaben zu THD im Dictionary <code>raum1</code> und die Angaben zu T1.3.08 im Dictionary <code>raum2</code>. Wähle selbst aussagekräftige Schlüssel und passende Datentypen.</p><p>Ein Boolean (Wahrheitswert) beschreibt, ob etwas zutrifft: In Python steht <code>True</code> für wahr und <code>False</code> für falsch. Beide werden ohne Anführungszeichen geschrieben. Verwende einen solchen Wert für die Beamerausstattung.</p><p>Ausgabe: zuerst Bezeichnung und Anzahl der Plätze von <code>raum1</code>, danach Bezeichnung und Anzahl der Plätze von <code>raum2</code>, insgesamt vier Zeilen ohne Beschriftung und aus den Dictionaries gelesen.</p><p>Entwirf die Dictionaries zunächst ohne Hinweis. Gleiche vor dem Check deine Schlüssel und Datentypen mit den Vorgaben im Hinweis ab. Der Check akzeptiert keine abweichenden Schlüssel.</p>',
        'hint1' => 'Für den automatischen Check müssen raum1 und raum2 jeweils genau drei Einträge enthalten: bezeichnung als Zeichenkette, plaetze als ganze Zahl und beamer als Wahrheitswert. Die Reihenfolge der Einträge ist beliebig.',
        'code_template' => "#raum1={}\n#raum2={}\n",
        'init' => '',
        'body' => "raum1 = {\"bezeichnung\": \"THD\", \"plaetze\": 80, \"beamer\": True}\nraum2 = {\"bezeichnung\": \"T1.3.08\", \"plaetze\": 20, \"beamer\": False}\nprint(raum1[\"bezeichnung\"])\nprint(raum1[\"plaetze\"])\nprint(raum2[\"bezeichnung\"])\nprint(raum2[\"plaetze\"])",
        'inputs' => [],
        'outputs' => ['raum1', 'raum2'],
        'cases' => [
            ['init_values' => [], 'expected_values' => [['bezeichnung' => 'THD', 'plaetze' => 80, 'beamer' => true], ['bezeichnung' => 'T1.3.08', 'plaetze' => 20, 'beamer' => false]]],
        ],
        'pattern' => '^THD\\s*\\r?\\n\\s*80\\s*\\r?\\n\\s*T1\\.3\\.08\\s*\\r?\\n\\s*20$',
    ],
    [
        'id' => null,
        'title' => 'Dictionary: Abteilungskontakte verschachteln',
        'task_text' => 'Organisiere die Kontaktdaten von Einkauf und Verkauf in einem verschachtelten Dictionary. Lies daraus die Leitung des Verkaufs und die Telefonnummer des Einkaufs aus und gib beide aus.',
        'description' => '<p>Die vorgegebenen Variablen enthalten jeweils Leitung und Telefonnummer von Einkauf und Verkauf.</p><p>Organisiere die Angaben im verschachtelten Dictionary <code>abteilungen</code>, sodass die Kontaktdaten jeder Abteilung zusammengehören. Wähle selbst eine geeignete Gliederung und aussagekräftige Schlüssel. Die Eingabevariablen bleiben unverändert.</p><p>Speichere die ausgelesenen Werte in <code>leitung_verkauf</code> und <code>telefon_einkauf</code>. Ausgabe: zuerst die Leitung, dann die Telefonnummer, jeweils in einer eigenen Zeile ohne Beschriftung. Der Code soll auch für andere Kontaktdaten funktionieren.</p><p>Entwirf die Struktur zunächst ohne Hinweis. Gleiche vor dem Check deine Gliederung und Schlüssel mit den Vorgaben im Hinweis ab. Der Check erwartet genau diese Struktur.</p>',
        'hint1' => 'Für den automatischen Check enthält abteilungen genau die Schlüssel einkauf und verkauf. Jeder Eintrag ist ein Dictionary mit genau den Schlüsseln leitung und telefon. Telefonnummern bleiben Zeichenketten. Die Reihenfolge der Einträge ist auf beiden Ebenen beliebig.',
        'init' => "einkauf_leitung = \"Mara Wolf\"\neinkauf_telefon = \"07231-100\"\nverkauf_leitung = \"Ali Kaya\"\nverkauf_telefon = \"07231-200\"",
        'body' => "abteilungen = {\n    \"einkauf\": {\"leitung\": einkauf_leitung, \"telefon\": einkauf_telefon},\n    \"verkauf\": {\"leitung\": verkauf_leitung, \"telefon\": verkauf_telefon}\n}\nleitung_verkauf = abteilungen[\"verkauf\"][\"leitung\"]\ntelefon_einkauf = abteilungen[\"einkauf\"][\"telefon\"]\nprint(leitung_verkauf)\nprint(telefon_einkauf)",
        'inputs' => ['einkauf_leitung', 'einkauf_telefon', 'verkauf_leitung', 'verkauf_telefon'],
        'outputs' => ['abteilungen', 'leitung_verkauf', 'telefon_einkauf', 'einkauf_leitung', 'einkauf_telefon', 'verkauf_leitung', 'verkauf_telefon'],
        'randomizer' => "import random\nvalues = {\"einkauf_leitung\": random.choice([\"Eva Roth\", \"Jan Kurz\"]), \"einkauf_telefon\": \"001-\" + str(random.randint(100, 999)), \"verkauf_leitung\": random.choice([\"Kim Lang\", \"Noah Berg\"]), \"verkauf_telefon\": \"002-\" + str(random.randint(100, 999))}",
        'cases' => [
            ['init_values' => ['Eva', '00100', 'Kim', '00200'], 'expected_values' => [['einkauf' => ['leitung' => 'Eva', 'telefon' => '00100'], 'verkauf' => ['leitung' => 'Kim', 'telefon' => '00200']], 'Kim', '00100', 'Eva', '00100', 'Kim', '00200']],
        ],
        'pattern' => '^Ali Kaya\\s*\\r?\\n\\s*07231-100$',
    ],
    [
        'id' => null,
        'title' => 'Dictionary und Liste: auf eine Bestellung zugreifen',
        'task_text' => 'Lies aus der Bestellung den Kundennamen und die Bezeichnung des zweiten Artikels aus. Berechne außerdem den Gesamtpreis der ersten Bestellposition. Gib die drei Ergebnisse aus.',
        'description' => '<p><code>bestellung</code> ist eine gemischte, verschachtelte Liste mit Bestellnummer, Kundendaten und genau zwei Bestellpositionen. Die Datenstruktur ist im Startcode vorgegeben; <code>preis</code> bezeichnet jeweils den Stückpreis.</p><p>Erwartet werden <code>kundenname</code>, <code>zweiter_artikel</code> und <code>positionswert</code> (Gesamtpreis der ersten Position). Die Bestellung bleibt unverändert.</p><p>Ausgabe: Kundenname, zweiter Artikel und Positionswert, jeweils in einer eigenen Zeile ohne Beschriftung. Der Code soll auch bei geänderten Bestelldaten funktionieren.</p>',
        'init' => "bestellung = [\n    \"B-1042\",\n    {\"kunde\": \"Mara Wolf\"},\n    [\n        {\"artikel\": \"Heft\", \"menge\": 3, \"preis\": 2.5},\n        {\"artikel\": \"Ordner\", \"menge\": 2, \"preis\": 4.0}\n    ]\n]",
        'body' => "kundenname = bestellung[1][\"kunde\"]\nzweiter_artikel = bestellung[2][1][\"artikel\"]\npositionswert = bestellung[2][0][\"menge\"] * bestellung[2][0][\"preis\"]\nprint(kundenname)\nprint(zweiter_artikel)\nprint(positionswert)",
        'inputs' => ['bestellung'],
        'outputs' => ['kundenname', 'zweiter_artikel', 'positionswert', 'bestellung'],
        'randomizer' => "import random\nvalues = {\"bestellung\": [\"B-\" + str(random.randint(1000, 9999)), {\"kunde\": random.choice([\"Eva Roth\", \"Ali Kaya\"])}, [{\"artikel\": \"Stift\", \"menge\": random.randint(0, 20), \"preis\": random.randint(0, 80) / 4}, {\"artikel\": random.choice([\"Mappe\", \"Papier\"]), \"menge\": random.randint(1, 20), \"preis\": random.randint(1, 80) / 4}]]}",
        'cases' => [
            ['init_values' => [['B-2001', ['kunde' => 'Eva'], [['artikel' => 'X', 'menge' => 0, 'preis' => 9], ['artikel' => 'Y', 'menge' => 8, 'preis' => 2]]]], 'expected_values' => ['Eva', 'Y', 0, ['B-2001', ['kunde' => 'Eva'], [['artikel' => 'X', 'menge' => 0, 'preis' => 9], ['artikel' => 'Y', 'menge' => 8, 'preis' => 2]]]]],
            ['init_values' => [['B-2002', ['kunde' => 'Kim'], [['artikel' => 'A', 'menge' => 5, 'preis' => 1.25], ['artikel' => 'B', 'menge' => 2, 'preis' => 11]]]], 'expected_values' => ['Kim', 'B', 6.25, ['B-2002', ['kunde' => 'Kim'], [['artikel' => 'A', 'menge' => 5, 'preis' => 1.25], ['artikel' => 'B', 'menge' => 2, 'preis' => 11]]]]],
        ],
        'pattern' => '^Mara Wolf\\s*\\r?\\n\\s*Ordner\\s*\\r?\\n\\s*7\\.50*$',
    ],
];

foreach ($tasks as &$task) {
    $task['code_template'] = $task['code_template'] ?? ($task['init'] === '' ? '' : "#INIT START\n" . $task['init'] . "\n#INIT END\n\n");
    $task['solution_code'] = $task['code_template'] . $task['body'];
    $task['test_cases'] = [];
    if (isset($task['randomizer'])) {
        $task['test_cases'][] = ['type' => 'intelligent', 'mode' => 'vars', 'tests' => 8, 'inputs' => $task['inputs'], 'outputs' => $task['outputs'], 'value_comparison' => 'structural'];
    }
    $task['test_cases'][] = ['type' => 'variable', 'init_var_names' => $task['inputs'], 'expected_var_names' => $task['outputs'], 'test_cases' => $task['cases']];
    $task['test_cases'][] = ['type' => 'output', 'expected_type' => 'regex', 'expected' => $task['pattern'], 'case_sensitive' => true, 'feedback' => 'Prüfe die verlangten Ausgabezeilen und ihre Reihenfolge.'];
}
unset($task);

if (in_array('--definitions', $argv, true)) {
    echo json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    exit;
}
if (!in_array('--apply', $argv, true)) {
    exit("Use --definitions or --apply\n");
}

define('BETA_LIVE_ALLOW_WRITE', true);
require_once __DIR__ . '/../config/database.beta_live.local.php';
require_once __DIR__ . '/../components/task_order.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = getBetaLiveDbConnection();
$conn->begin_transaction();
try {
    lockAssignmentTaskOrder($conn, 48);
    $assignment = $conn->query('SELECT locked FROM assignments WHERE id=48')->fetch_assoc();
    if ((int)$assignment['locked'] !== 0) {
        throw new RuntimeException('Assignment 48 must be unlocked');
    }
    $before = $conn->query('SELECT * FROM tasks WHERE assignment_id=48 ORDER BY position,id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
    $byId = array_column($before, null, 'id');
    $progress = $conn->query('SELECT task_id FROM user_tasks WHERE task_id IN (569,570) LIMIT 1 FOR UPDATE')->fetch_assoc();
    if ($progress) {
        throw new RuntimeException('Saved progress exists; explicit consent required before replacing these tasks');
    }
    $anchor = $byId[546] ?? null;
    if (!$anchor || $anchor['title'] !== 'Funktion: verdreifachen') {
        throw new RuntimeException('Could not find expected insertion anchor');
    }
    foreach ([569 => 'Daten organisieren: Raummessungen als Dictionaries', 570 => 'Dictionaries auswerten: Materialbedarf zusammenfassen'] as $id => $title) {
        if (!isset($byId[$id]) || $byId[$id]['title'] !== $title || (int)$byId[$id]['locked'] !== 0) {
            throw new RuntimeException('Original task changed or locked; reread before updating');
        }
    }
    $fingerprints = [
        569 => '6ce887f24959a9d33b023b935b230bd2de41ecfdb1d487d66c5324b19e89baef',
        570 => '19a5d3a7805a2fa159cfee91b5bd8a4b1055562f2c7cbc65636d24c51dc6ab18',
    ];
    foreach ($fingerprints as $id => $expected) {
        if (hash('sha256', json_encode($byId[$id], JSON_THROW_ON_ERROR)) !== $expected) {
            throw new RuntimeException('Live task changed since review; refusing to overwrite task ' . $id);
        }
    }
    $backupPath = getenv('DICTIONARY_REVISION_BACKUP');
    if (!$backupPath || file_put_contents($backupPath, json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Set DICTIONARY_REVISION_BACKUP to a session backup file');
    }
    $blockIds = [];
    foreach ($tasks as $task) {
        $id = $task['id'];
        $tests = json_encode($task['test_cases'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $randomizer = $task['randomizer'] ?? null;
        if ($id !== null) {
            $hint1 = $task['hint1'] ?? null;
            $stmt = $conn->prepare('UPDATE tasks SET title=?, task_text=?, description=?, code_template=?, solution_code=?, test_cases=?, randomizer_code=?, hint1=?, hint2=NULL, hint3=NULL, updated_at=NOW() WHERE id=? AND assignment_id=48');
            $stmt->bind_param('ssssssssi', $task['title'], $task['task_text'], $task['description'], $task['code_template'], $task['solution_code'], $tests, $randomizer, $hint1, $id);
        } else {
            $title = $conn->real_escape_string($task['title']);
            if ($conn->query("SELECT id FROM tasks WHERE assignment_id=48 AND title='$title'")->num_rows !== 0) {
                throw new RuntimeException('Task already exists; refusing to create duplicate');
            }
            $hint1 = $task['hint1'] ?? null;
            $stmt = $conn->prepare("INSERT INTO tasks (assignment_id,title,task_text,description,code_template,solution_code,test_cases,randomizer_code,hint1,task_type,problem_type,task_difficulty,position,locked,max_attempts,show_solution,show_solution_code,created_at,updated_at) VALUES (48,?,?,?,?,?,?,?,?,'code','code_completion','basic',999,0,10,1,0,NOW(),NOW())");
            $stmt->bind_param('ssssssss', $task['title'], $task['task_text'], $task['description'], $task['code_template'], $task['solution_code'], $tests, $randomizer, $hint1);
        }
        $stmt->execute();
        $blockIds[] = $id ?? (int)$conn->insert_id;
    }
    // Move the entire block, preserving all other task ordering.
    $otherIds = array_values(array_filter(array_map('intval', array_column($before, 'id')), static function (int $id): bool { return !in_array($id, [569,570], true); }));
    $index = array_search(546, $otherIds, true);
    array_splice($otherIds, $index, 0, $blockIds);
    $stmt = $conn->prepare('UPDATE tasks SET position=? WHERE id=? AND assignment_id=48');
    foreach ($otherIds as $index => $id) {
        $position = $index + 1;
        $stmt->bind_param('ii', $position, $id);
        $stmt->execute();
    }
    normalizeTaskPositions($conn, 48);
    $after = $conn->query('SELECT * FROM tasks WHERE assignment_id=48 ORDER BY position,id')->fetch_all(MYSQLI_ASSOC);
    foreach ($after as $row) {
        $id = (int)$row['id'];
        if (!in_array($id, $blockIds, true)) {
            $old = $byId[$id];
            unset($old['position'], $old['updated_at'], $row['position'], $row['updated_at']);
            if ($old !== $row) {
                throw new RuntimeException('Unrelated task changed');
            }
        }
    }
    $conn->commit();
    echo json_encode(['task_ids' => $blockIds, 'before_task' => 546, 'total_tasks' => count($after)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    $conn->rollback();
    throw $error;
}
