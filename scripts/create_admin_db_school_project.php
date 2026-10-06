<?php
/**
 * Create one db_small project for the admin user that already owns db_small projects.
 * Model: schueler, klasse, lehrer with keys and references.
 */

require_once __DIR__ . '/../config/database.php';

$conn = getDbConnection();

function fail_and_exit(string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$userId = 0;
$userEmail = '';

$ownerStmt = $conn->prepare(
    "SELECT u.id, u.email, COUNT(*) AS cnt
     FROM users u
     INNER JOIN projects p ON p.user_id = u.id
     WHERE p.project_type = 'db_small'
     GROUP BY u.id, u.email
     ORDER BY cnt DESC, u.id ASC
     LIMIT 1"
);
if (!$ownerStmt || !$ownerStmt->execute()) {
    fail_and_exit('Could not resolve db_small owner user.');
}
$ownerRow = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if ($ownerRow) {
    $userId = (int)$ownerRow['id'];
    $userEmail = (string)($ownerRow['email'] ?? '');
} else {
    $adminStmt = $conn->prepare("SELECT id, email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    if (!$adminStmt || !$adminStmt->execute()) {
        fail_and_exit('Could not resolve admin user.');
    }
    $adminRow = $adminStmt->get_result()->fetch_assoc();
    $adminStmt->close();
    if (!$adminRow) {
        fail_and_exit('No admin user found.');
    }
    $userId = (int)$adminRow['id'];
    $userEmail = (string)($adminRow['email'] ?? '');
}

$projectName = 'DB Schulverwaltung (Schueler-Klasse-Lehrer) ' . date('Y-m-d H:i');
$projectDescription = '3 Tabellen mit Beziehungen: schueler -> klasse, klasse -> lehrer (klassenlehrer).';

$dbModel = [
    'version' => 3,
    'activeDatabaseIndex' => 0,
    'databases' => [
        [
            'id' => 'db_school',
            'name' => 'Schulverwaltung',
            'tables' => [
                [
                    'id' => 'tbl_lehrer',
                    'name' => 'lehrer',
                    'columns' => [
                        ['id' => 'col_lehrer_id', 'name' => 'id', 'type' => 'INTEGER', 'pk' => true, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_lehrer_name', 'name' => 'name', 'type' => 'TEXT', 'pk' => false, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_lehrer_email', 'name' => 'email', 'type' => 'TEXT', 'pk' => false, 'fk' => false, 'nullable' => true, 'default' => '', 'references' => null]
                    ],
                    'rows' => [
                        ['id' => '1', 'name' => 'Frau Weber', 'email' => 'weber@schule.local'],
                        ['id' => '2', 'name' => 'Herr Klein', 'email' => 'klein@schule.local']
                    ]
                ],
                [
                    'id' => 'tbl_klasse',
                    'name' => 'klasse',
                    'columns' => [
                        ['id' => 'col_klasse_id', 'name' => 'id', 'type' => 'INTEGER', 'pk' => true, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_klasse_name', 'name' => 'name', 'type' => 'TEXT', 'pk' => false, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_klasse_klassenlehrer', 'name' => 'klassenlehrer_id', 'type' => 'INTEGER', 'pk' => false, 'fk' => true, 'nullable' => false, 'default' => '', 'references' => ['table' => 'lehrer', 'column' => 'id', 'onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION']]
                    ],
                    'rows' => [
                        ['id' => '1', 'name' => '10A', 'klassenlehrer_id' => '1'],
                        ['id' => '2', 'name' => '10B', 'klassenlehrer_id' => '2']
                    ]
                ],
                [
                    'id' => 'tbl_schueler',
                    'name' => 'schueler',
                    'columns' => [
                        ['id' => 'col_schueler_id', 'name' => 'id', 'type' => 'INTEGER', 'pk' => true, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_schueler_vorname', 'name' => 'vorname', 'type' => 'TEXT', 'pk' => false, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_schueler_nachname', 'name' => 'nachname', 'type' => 'TEXT', 'pk' => false, 'fk' => false, 'nullable' => false, 'default' => '', 'references' => null],
                        ['id' => 'col_schueler_klasse', 'name' => 'klasse_id', 'type' => 'INTEGER', 'pk' => false, 'fk' => true, 'nullable' => false, 'default' => '', 'references' => ['table' => 'klasse', 'column' => 'id', 'onUpdate' => 'NO ACTION', 'onDelete' => 'NO ACTION']]
                    ],
                    'rows' => [
                        ['id' => '1', 'vorname' => 'Mia', 'nachname' => 'Hoffmann', 'klasse_id' => '1'],
                        ['id' => '2', 'vorname' => 'Noah', 'nachname' => 'Schmidt', 'klasse_id' => '1'],
                        ['id' => '3', 'vorname' => 'Emil', 'nachname' => 'Bauer', 'klasse_id' => '2']
                    ]
                ]
            ]
        ]
    ]
];

$dbModelJson = json_encode($dbModel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($dbModelJson === false) {
    fail_and_exit('Failed to encode db_model.json');
}

$dbExportSql = <<<SQL
CREATE TABLE "lehrer" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "email" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "klasse" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "klassenlehrer_id" INTEGER NOT NULL,
  PRIMARY KEY ("id"),
  FOREIGN KEY ("klassenlehrer_id") REFERENCES "lehrer" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION
);

CREATE TABLE "schueler" (
  "id" INTEGER NOT NULL,
  "vorname" TEXT NOT NULL,
  "nachname" TEXT NOT NULL,
  "klasse_id" INTEGER NOT NULL,
  PRIMARY KEY ("id"),
  FOREIGN KEY ("klasse_id") REFERENCES "klasse" ("id") ON UPDATE NO ACTION ON DELETE NO ACTION
);

INSERT INTO "lehrer" ("id", "name", "email") VALUES (1, 'Frau Weber', 'weber@schule.local');
INSERT INTO "lehrer" ("id", "name", "email") VALUES (2, 'Herr Klein', 'klein@schule.local');
INSERT INTO "klasse" ("id", "name", "klassenlehrer_id") VALUES (1, '10A', 1);
INSERT INTO "klasse" ("id", "name", "klassenlehrer_id") VALUES (2, '10B', 2);
INSERT INTO "schueler" ("id", "vorname", "nachname", "klasse_id") VALUES (1, 'Mia', 'Hoffmann', 1);
INSERT INTO "schueler" ("id", "vorname", "nachname", "klasse_id") VALUES (2, 'Noah', 'Schmidt', 1);
INSERT INTO "schueler" ("id", "vorname", "nachname", "klasse_id") VALUES (3, 'Emil', 'Bauer', 2);
SQL;

$conn->begin_transaction();

try {
    $insertProject = $conn->prepare('INSERT INTO projects (user_id, name, description, code, project_type, visibility, share_token) VALUES (?, ?, ?, ?, ?, ?, NULL)');
    if (!$insertProject) {
        throw new RuntimeException('Prepare project insert failed: ' . $conn->error);
    }

    $emptyCode = '';
    $projectType = 'db_small';
    $visibility = 'private';
    $insertProject->bind_param('isssss', $userId, $projectName, $projectDescription, $emptyCode, $projectType, $visibility);

    if (!$insertProject->execute()) {
        throw new RuntimeException('Project insert failed: ' . $insertProject->error);
    }

    $projectId = (int)$conn->insert_id;
    $insertProject->close();

    $folderStmt = $conn->prepare('INSERT INTO project_folders (project_id, parent_folder_id, name) VALUES (?, NULL, ?)');
    if ($folderStmt) {
        $folderName = 'includes';
        $folderStmt->bind_param('is', $projectId, $folderName);
        $folderStmt->execute();
        $folderName = 'img';
        $folderStmt->bind_param('is', $projectId, $folderName);
        $folderStmt->execute();
        $folderStmt->close();
    }

    $fileStmt = $conn->prepare('INSERT INTO project_files (project_id, folder_id, name, content, mime_type, file_size) VALUES (?, NULL, ?, ?, ?, ?)');
    if (!$fileStmt) {
        throw new RuntimeException('Prepare file insert failed: ' . $conn->error);
    }

    $files = [
        ['name' => 'init.py', 'content' => "# DB Small Projekt\n# Schulverwaltung: Schueler, Klasse, Lehrer\n", 'mime' => 'text/x-python'],
        ['name' => 'db_model.json', 'content' => $dbModelJson, 'mime' => 'application/json'],
        ['name' => 'db_export.sql', 'content' => $dbExportSql . "\n", 'mime' => 'application/sql']
    ];

    foreach ($files as $file) {
        $name = $file['name'];
        $content = $file['content'];
        $mime = $file['mime'];
        $size = strlen($content);
        $fileStmt->bind_param('isssi', $projectId, $name, $content, $mime, $size);
        if (!$fileStmt->execute()) {
            throw new RuntimeException('Failed to create file ' . $name . ': ' . $fileStmt->error);
        }
    }
    $fileStmt->close();

    $conn->commit();

    echo "OK\n";
    echo 'user_id=' . $userId . "\n";
    echo 'user_email=' . $userEmail . "\n";
    echo 'project_id=' . $projectId . "\n";
    echo 'project_name=' . $projectName . "\n";
} catch (Throwable $e) {
    $conn->rollback();
    fail_and_exit('ERROR: ' . $e->getMessage());
}
