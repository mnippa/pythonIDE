<?php
/**
 * Migration 044: Update solution_code for task #12 Rechnung III (while without break).
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $conn = getDbConnection();
    echo "Running Migration 044: update solution code for #12...\n";

    $solutionCode = <<<'PY'
rechnung = []
gesamt_summe = 0.0
pos = 1

name = input("Name der Position (leer = Ende): ")
while name != "":
    art = input("Art (Getraenk/Speise): ")
    anzahl = int(input("Anzahl: "))
    einzelpreis = float(input("Einzelpreis: "))

    positionssumme = anzahl * einzelpreis
    eintrag = {
        "position": pos,
        "name": name,
        "art": art,
        "anzahl": anzahl,
        "einzelpreis": einzelpreis,
        "positionssumme": positionssumme
    }
    rechnung.append(eintrag)

    gesamt_summe = gesamt_summe + positionssumme
    pos = pos + 1
    name = input("Name der Position (leer = Ende): ")

print(rechnung)
print(gesamt_summe)
PY;

    $taskTitle = '#12 Rechnung III';

    $stmt = $conn->prepare(
        'UPDATE tasks t
         JOIN assignments a ON a.id = t.assignment_id
         SET t.solution_code = ?, t.updated_at = NOW()
         WHERE a.title = ? AND t.title = ?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $assignmentTitle = 'C: Bedingungen und Schleifen';
    $stmt->bind_param('sss', $solutionCode, $assignmentTitle, $taskTitle);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected <= 0) {
        echo "⚠ No rows updated (task not found or already identical).\n";
    } else {
        echo "✓ Updated rows: {$affected}\n";
    }

    echo "\n✅ Migration 044: Success!\n";
    $conn->close();
} catch (Exception $e) {
    echo "❌ Migration 044 failed: " . $e->getMessage() . "\n";
    exit(1);
}
