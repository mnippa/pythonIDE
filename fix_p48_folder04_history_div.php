<?php
require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

$projectId = 48;
$folderId = 122;

function loadFileContent(mysqli $conn, int $projectId, int $folderId, string $name): ?string {
    $stmt = $conn->prepare('SELECT content FROM project_files WHERE project_id=? AND folder_id=? AND name=? LIMIT 1');
    $stmt->bind_param('iis', $projectId, $folderId, $name);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        return null;
    }
    $row = $res->fetch_assoc();
    return (string)($row['content'] ?? '');
}

function saveFileContent(mysqli $conn, int $projectId, int $folderId, string $name, string $content): void {
    $size = strlen($content);
    $stmt = $conn->prepare('UPDATE project_files SET content=?, file_size=?, updated_at=NOW() WHERE project_id=? AND folder_id=? AND name=?');
    $stmt->bind_param('siiis', $content, $size, $projectId, $folderId, $name);
    $stmt->execute();
    echo $name . ': ' . $stmt->affected_rows . " row(s) updated\n";
}

$index = loadFileContent($conn, $projectId, $folderId, 'index.html');
$css = loadFileContent($conn, $projectId, $folderId, 'style.css');

if ($index === null || $css === null) {
    echo "Fehler: index.html oder style.css nicht gefunden.\n";
    exit(1);
}

$oldHtml = "      <div class=\"form-group\" style=\"margin-top:18px;\">\n        <label for=\"verlauf\">Verlauf (neueste zuerst)</label>\n        <textarea id=\"verlauf\" data-element=\"verlauf\" rows=\"6\" readonly></textarea>\n      </div>";
$newHtml = "      <div class=\"form-group\" style=\"margin-top:18px;\">\n        <label for=\"verlauf\">Verlauf (neueste zuerst)</label>\n        <div id=\"verlauf\" data-element=\"verlauf\" class=\"history-box\"></div>\n      </div>";

if (strpos($index, $oldHtml) === false) {
    echo "Warnung: Erwarteter HTML-Block nicht gefunden, keine HTML-Aenderung.\n";
} else {
    $index = str_replace($oldHtml, $newHtml, $index);
    saveFileContent($conn, $projectId, $folderId, 'index.html', $index);
}

$oldCss = ".codeui-app textarea {\n  width: 100%;\n  padding: 10px 12px;\n  border: 2px solid var(--gray-200);\n  border-radius: 8px;\n  font-size: 14px;\n  font-family: 'Courier New', Courier, monospace;\n  background: var(--gray-50);\n  color: var(--gray-700);\n  resize: vertical;\n}";
$newCss = ".codeui-app .history-box {\n  width: 100%;\n  min-height: 130px;\n  max-height: 180px;\n  overflow-y: auto;\n  padding: 10px 12px;\n  border: 2px solid var(--gray-200);\n  border-radius: 8px;\n  font-size: 14px;\n  font-family: 'Courier New', Courier, monospace;\n  background: var(--gray-50);\n  color: var(--gray-700);\n  white-space: pre-wrap;\n}";

if (strpos($css, $oldCss) === false) {
    echo "Warnung: Erwarteter CSS-Block nicht gefunden, keine CSS-Aenderung.\n";
} else {
    $css = str_replace($oldCss, $newCss, $css);
    saveFileContent($conn, $projectId, $folderId, 'style.css', $css);
}

echo "Done.\n";
