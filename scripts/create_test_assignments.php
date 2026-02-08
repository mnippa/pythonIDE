<?php
/**
 * Create test assignments with example tasks
 * Run: php scripts/create_test_assignments.php
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

function getAdminUserId($conn) {
    $email = 'admin@pythonide.local';
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? (int)$row['id'] : null;
}

function getAssignmentByTitle($conn, $title) {
    $stmt = $conn->prepare('SELECT id FROM assignments WHERE title = ? LIMIT 1');
    $stmt->bind_param('s', $title);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getTaskCount($conn, $assignmentId) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS count FROM tasks WHERE assignment_id = ?');
    $stmt->bind_param('i', $assignmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)$row['count'];
}

function insertAssignment($conn, $title, $description, $createdBy, $difficulty) {
    $stmt = $conn->prepare(
        'INSERT INTO assignments (title, description, created_by, is_active, difficulty) VALUES (?, ?, ?, 1, ?)'
    );
    $stmt->bind_param('ssis', $title, $description, $createdBy, $difficulty);
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert assignment: ' . $conn->error);
    }
    return (int)$conn->insert_id;
}

function insertTask($conn, $assignmentId, $title, $description, $position, $problemType, $codeTemplate, $hint, $expectedOutput) {
    $stmt = $conn->prepare(
        'INSERT INTO tasks (assignment_id, title, description, position, problem_type, code_template, hint, expected_output)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ississss',
        $assignmentId,
        $title,
        $description,
        $position,
        $problemType,
        $codeTemplate,
        $hint,
        $expectedOutput
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to insert task: ' . $conn->error);
    }
}

try {
    echo "========================================\n";
    echo "Creating Test Assignments\n";
    echo "========================================\n\n";

    $adminId = getAdminUserId($conn);
    if (!$adminId) {
        throw new Exception('Admin user not found (admin@pythonide.local)');
    }

    // Assignment 1: Conditions
    $title1 = 'Bedingungen Grundlagen';
    $desc1 = 'Einstieg in If/Else, Vergleichsoperatoren und logische Operatoren.';
    $difficulty1 = 'beginner';

    $assignment = getAssignmentByTitle($conn, $title1);
    if ($assignment) {
        $assignmentId1 = (int)$assignment['id'];
        echo "- Assignment already exists: {$title1} (ID: {$assignmentId1})\n";
    } else {
        $assignmentId1 = insertAssignment($conn, $title1, $desc1, $adminId, $difficulty1);
        echo "- Created assignment: {$title1} (ID: {$assignmentId1})\n";
    }

    if (getTaskCount($conn, $assignmentId1) === 0) {
        insertTask(
            $conn,
            $assignmentId1,
            'Vergleichsoperatoren',
            'Setze result auf True, wenn x groesser als y ist.',
            1,
            'code_completion',
            "x = 10\ny = 5\n# TODO: setze result auf True wenn x > y, sonst False\nresult = ___\nprint(result)",
            'Nutze einen Vergleichsoperator.',
            "True"
        );

        insertTask(
            $conn,
            $assignmentId1,
            'Gerade oder ungerade',
            'Gib "odd" aus, wenn die Zahl ungerade ist, sonst "even".',
            2,
            'code_completion',
            "n = 7\n# TODO: gib \"odd\" oder \"even\" aus\nif ___:\n    print(\"odd\")\nelse:\n    print(\"even\")",
            'Teste mit n % 2.',
            "odd"
        );

        insertTask(
            $conn,
            $assignmentId1,
            'Notenlogik',
            'Gib die Note A/B/C/D basierend auf score aus.',
            3,
            'code_completion',
            "score = 82\n# TODO: A >= 90, B >= 80, C >= 70, sonst D\nif score >= ___:\n    print(\"A\")\nelif score >= ___:\n    print(\"B\")\nelif score >= ___:\n    print(\"C\")\nelse:\n    print(\"D\")",
            'Pruefe von oben nach unten.',
            "B"
        );

        insertTask(
            $conn,
            $assignmentId1,
            'Bereichspruefung',
            'Gib "allowed" aus, wenn beide Bedingungen erfuellt sind.',
            4,
            'code_completion',
            "age = 19\nhas_ticket = True\n# TODO: erlauben wenn age >= 18 und has_ticket\nif ___ and ___:\n    print(\"allowed\")\nelse:\n    print(\"denied\")",
            'Kombiniere Bedingungen mit and.',
            "allowed"
        );

        echo "  -> Added 4 tasks to {$title1}\n";
    } else {
        echo "  -> Tasks already exist for {$title1}, skipping\n";
    }

    // Assignment 2: Loops + Conditions
    $title2 = 'Schleifen und Bedingungen';
    $desc2 = 'Schleifen mit If/Else, break und kombinierte Logik.';
    $difficulty2 = 'intermediate';

    $assignment = getAssignmentByTitle($conn, $title2);
    if ($assignment) {
        $assignmentId2 = (int)$assignment['id'];
        echo "- Assignment already exists: {$title2} (ID: {$assignmentId2})\n";
    } else {
        $assignmentId2 = insertAssignment($conn, $title2, $desc2, $adminId, $difficulty2);
        echo "- Created assignment: {$title2} (ID: {$assignmentId2})\n";
    }

    if (getTaskCount($conn, $assignmentId2) === 0) {
        insertTask(
            $conn,
            $assignmentId2,
            'Gerade Zahlen zaehlen',
            'Zaehle die geraden Zahlen in der Liste.',
            1,
            'code_completion',
            "nums = [1, 2, 3, 4, 5, 6]\ncount = 0\n# TODO: zaehle gerade Zahlen\nfor n in nums:\n    if ___:\n        count += 1\nprint(count)",
            'Eine Zahl ist gerade, wenn n % 2 == 0.',
            "3"
        );

        insertTask(
            $conn,
            $assignmentId2,
            'Summe bis Grenze',
            'Summiere Zahlen, bis total die Grenze erreicht.',
            2,
            'code_completion',
            "limit = 10\ni = 1\ntotal = 0\n# TODO: summe addieren, solange total < limit\nwhile total < ___:\n    total += i\n    i += 1\nprint(total)",
            'Nutze eine while-Schleife.',
            "10"
        );

        insertTask(
            $conn,
            $assignmentId2,
            'Break bei Treffer',
            'Stoppe die Schleife, sobald ein Wert >= target gefunden wird.',
            3,
            'code_completion',
            "values = [3, 5, 8, 10, 12]\ntarget = 10\nfound = None\n# TODO: finde den ersten Wert >= target\nfor v in values:\n    if v >= ___:\n        found = v\n        break\nprint(found)",
            'Nutze break bei Treffer.',
            "10"
        );

        insertTask(
            $conn,
            $assignmentId2,
            'Kombinierte Bedingungen',
            'Zaehle Zahlen von 1 bis 30, die durch 3 teilbar und nicht durch 2 teilbar sind.',
            4,
            'code_completion',
            "count = 0\nfor i in range(1, 31):\n    if i % 3 == 0 and i % 2 != 0:\n        count += 1\nprint(count)",
            'Kombiniere Modulo-Bedingungen.',
            "5"
        );

        echo "  -> Added 4 tasks to {$title2}\n";
    } else {
        echo "  -> Tasks already exist for {$title2}, skipping\n";
    }

    echo "\n========================================\n";
    echo "Done\n";
    echo "========================================\n";

} catch (Exception $e) {
    echo "\nError: " . $e->getMessage() . "\n";
    exit(1);
}
