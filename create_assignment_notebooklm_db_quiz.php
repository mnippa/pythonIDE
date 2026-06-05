<?php
/**
 * Create Assignment from NotebookLM quiz: Datenbanken Quiz
 * Source artifact: notebook/862cd99d.../artifact/7eea490f...
 */

require_once 'config/database.php';

$conn = getDbConnection();

$assignmentTitle = 'Datenbanken Quiz (NotebookLM)';
$assignmentDesc = 'MC-Assignment mit 10 Fragen zur UML->Relationale-Transformation, Schluesseln, Beziehungen und HeidiSQL.';
$createdBy = 1;

$tasks = [
    [
        'position' => 1,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 1: Klassen-Transformation',
        'description' => 'NotebookLM Quizfrage 1',
        'question_text' => "Was passiert im ersten Schritt der Transformation mit einer UML-Klasse wie 'Buch'?",
        'options' => [
            ['text' => 'Sie wird direkt als SQL-View implementiert.', 'is_correct' => 0],
            ['text' => 'Sie wird in eine Zeile innerhalb einer globalen Objekt-Tabelle umgewandelt.', 'is_correct' => 0],
            ['text' => 'Sie wird als eigenstaendige Tabelle in der Datenbank angelegt.', 'is_correct' => 1],
            ['text' => 'Sie wird in einen Datentyp innerhalb eines Schemas umgewandelt.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 2,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 2: Attribute',
        'description' => 'NotebookLM Quizfrage 2',
        'question_text' => "Wie werden die Attribute einer UML-Klasse (z. B. 'isbn', 'titel') in das relationale Modell ueberfuehrt?",
        'options' => [
            ['text' => 'Sie werden in externe Konfigurationsdateien ausgelagert.', 'is_correct' => 0],
            ['text' => 'Sie werden als Primaerschluessel fuer jede Beziehung verwendet.', 'is_correct' => 0],
            ['text' => 'Sie werden als Spalten der entsprechenden Tabelle definiert.', 'is_correct' => 1],
            ['text' => 'Sie werden als einzelne Datensaetze in die Tabelle eingefuegt.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 3,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 3: Technische IDs',
        'description' => 'NotebookLM Quizfrage 3',
        'question_text' => 'Warum werden in der Praxis oft technische IDs (Primaerschluessel) gegenueber natuerlichen Schluesseln wie der ISBN bevorzugt?',
        'options' => [
            ['text' => 'Natuerliche Schluessel koennen in relationalen Datenbanken technisch nicht gespeichert werden.', 'is_correct' => 0],
            ['text' => 'Technische IDs sind kuerzer und veraendern sich im Gegensatz zu fachlichen Daten nicht.', 'is_correct' => 1],
            ['text' => 'Technische IDs werden automatisch von UML-Diagrammen generiert.', 'is_correct' => 0],
            ['text' => 'Nur technische IDs erlauben die Verwendung von Fremdschluesseln.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 4,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 4: Fremdschluessel-Metapher',
        'description' => 'NotebookLM Quizfrage 4',
        'question_text' => 'Welche visuelle Metapher wird in der Vorlesung verwendet, um die Funktion eines Fremdschluessels zu verdeutlichen?',
        'options' => [
            ['text' => 'Ein Taschenrechner-Symbol.', 'is_correct' => 0],
            ['text' => 'Eine Kreuzung mit Ampeln.', 'is_correct' => 0],
            ['text' => 'Ein Verbindungskabel zwischen Tabellen.', 'is_correct' => 1],
            ['text' => 'Ein Foerderband.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 5,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 5: m:n Beziehung',
        'description' => 'NotebookLM Quizfrage 5',
        'question_text' => 'Wie muss eine m:n-Beziehung (z. B. Student * --- * Vorlesung) laut den Transformationsregeln aufgeloest werden?',
        'options' => [
            ['text' => 'Durch die Erstellung einer Zwischentabelle, die die Primaerschluessel beider Tabellen als Fremdschluessel enthaelt.', 'is_correct' => 1],
            ['text' => 'Durch Hinzufuegen einer Liste von IDs in eine einzelne Tabellenspalte.', 'is_correct' => 0],
            ['text' => 'Durch Verdoppeln der Datensaetze in beiden beteiligten Tabellen.', 'is_correct' => 0],
            ['text' => 'Die Beziehung wird im relationalen Modell ignoriert, da sie nur in UML existiert.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 6,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 6: Enumerationen',
        'description' => 'NotebookLM Quizfrage 6',
        'question_text' => 'Welche strikte Konvention gilt fuer die Transformation von Enumerationen in dieser Vorlesung?',
        'options' => [
            ['text' => 'Sie werden ausschliesslich als Referenztabellen mit technischer ID und Bezeichnung modelliert.', 'is_correct' => 1],
            ['text' => 'Enumerationen werden in der Datenbank durch einfache Integer-Werte ohne Bezeichnung ersetzt.', 'is_correct' => 0],
            ['text' => 'Sie werden als spezielle Datenbank-ENUM-Typen direkt im Schema definiert.', 'is_correct' => 0],
            ['text' => 'Sie werden als VARCHAR-Spalte mit einer Liste erlaubter Werte gespeichert.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 7,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 7: Abgeleitete Attribute',
        'description' => 'NotebookLM Quizfrage 7',
        'question_text' => "Was geschieht mit abgeleiteten UML-Attributen wie '/anzahlExemplare' bei der Ueberfuehrung in die Datenbank?",
        'options' => [
            ['text' => 'Sie werden als Primaerschluessel der Klasse verwendet.', 'is_correct' => 0],
            ['text' => "Sie werden in einer speziellen Tabelle fuer 'Berechnungen' gesammelt.", 'is_correct' => 0],
            ['text' => 'Sie werden als normale Spalten gespeichert, um die Performance zu erhoehen.', 'is_correct' => 0],
            ['text' => 'Sie werden grundsaetzlich gar nicht in der Datenbank gespeichert.', 'is_correct' => 1],
        ],
    ],
    [
        'position' => 8,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 8: Datentyp String',
        'description' => 'NotebookLM Quizfrage 8',
        'question_text' => "Welcher Datenbank-Datentyp wird laut Vorlesung ueblicherweise fuer ein UML-Attribut vom Typ 'String' verwendet?",
        'options' => [
            ['text' => 'CHAR', 'is_correct' => 0],
            ['text' => 'STRING_DB', 'is_correct' => 0],
            ['text' => 'VARCHAR', 'is_correct' => 1],
            ['text' => 'TEXT', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 9,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 9: 1:n Beziehung',
        'description' => 'NotebookLM Quizfrage 9',
        'question_text' => "In einem UML-Diagramm ist eine 1:n-Beziehung zwischen 'Kunde' und 'Ausleihe' definiert. Wo muss der Fremdschluessel in der Datenbank platziert werden?",
        'options' => [
            ['text' => "In der Tabelle 'Kunde'.", 'is_correct' => 0],
            ['text' => "In der Tabelle 'Ausleihe'.", 'is_correct' => 1],
            ['text' => 'In beiden Tabellen gleichzeitig.', 'is_correct' => 0],
            ['text' => 'In einer zusaetzlichen Zwischentabelle.', 'is_correct' => 0],
        ],
    ],
    [
        'position' => 10,
        'task_type' => 'multiple_choice',
        'title' => 'DB Quiz 10: HeidiSQL Ziel',
        'description' => 'NotebookLM Quizfrage 10',
        'question_text' => 'Was ist das Hauptziel der Verwendung von HeidiSQL in der Vorlesung?',
        'options' => [
            ['text' => 'Das Erstellen komplexer UML-Diagramme.', 'is_correct' => 0],
            ['text' => 'Die automatische Generierung von PowerPoint-Folien.', 'is_correct' => 0],
            ['text' => 'Das Programmieren von Web-Oberflaechen fuer die Bibliothek.', 'is_correct' => 0],
            ['text' => 'Die praktische Demonstration der theoretischen Konzepte an echten Daten.', 'is_correct' => 1],
        ],
    ],
];

$assignmentStmt = $conn->prepare('INSERT INTO assignments (title, description, created_by) VALUES (?, ?, ?)');
if (!$assignmentStmt) {
    die("Prepare assignment failed: " . $conn->error . "\n");
}
$assignmentStmt->bind_param('ssi', $assignmentTitle, $assignmentDesc, $createdBy);
if (!$assignmentStmt->execute()) {
    die("Create assignment failed: " . $assignmentStmt->error . "\n");
}
$assignmentId = (int)$conn->insert_id;
$assignmentStmt->close();

echo "Created Assignment #{$assignmentId}: {$assignmentTitle}\n";

$taskStmt = $conn->prepare(
    'INSERT INTO tasks (
        assignment_id, title, description, position, task_type,
        question_text, code_template, solution_code, correct_answer,
        show_solution
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$taskStmt) {
    die("Prepare task failed: " . $conn->error . "\n");
}

$optionStmt = $conn->prepare(
    'INSERT INTO task_options (task_id, option_text, is_correct, order_num) VALUES (?, ?, ?, ?)'
);
if (!$optionStmt) {
    die("Prepare option failed: " . $conn->error . "\n");
}

$createdTasks = 0;
foreach ($tasks as $task) {
    $codeTemplate = '';
    $solutionCode = '';
    $correctAnswer = '';
    $showSolution = 1;

    $taskStmt->bind_param(
        'issississi',
        $assignmentId,
        $task['title'],
        $task['description'],
        $task['position'],
        $task['task_type'],
        $task['question_text'],
        $codeTemplate,
        $solutionCode,
        $correctAnswer,
        $showSolution
    );

    if (!$taskStmt->execute()) {
        echo "Task insert failed at position {$task['position']}: " . $taskStmt->error . "\n";
        continue;
    }

    $taskId = (int)$conn->insert_id;

    foreach ($task['options'] as $idx => $opt) {
        $orderNum = $idx + 1;
        $isCorrect = !empty($opt['is_correct']) ? 1 : 0;
        $text = (string)$opt['text'];
        $optionStmt->bind_param('isii', $taskId, $text, $isCorrect, $orderNum);
        if (!$optionStmt->execute()) {
            echo "Option insert failed for task #{$taskId}: " . $optionStmt->error . "\n";
        }
    }

    $createdTasks++;
    echo "  - Task {$task['position']} created (task_id={$taskId})\n";
}

$taskStmt->close();
$optionStmt->close();
$conn->close();

echo "Done. Assignment ID: {$assignmentId}, tasks created: {$createdTasks}/" . count($tasks) . "\n";
