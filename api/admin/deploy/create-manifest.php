<?php

declare(strict_types=1);

require_once __DIR__ . '/../../auth/middleware.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function json_error(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalize_compare_source(?string $value): string {
    $candidate = strtolower(trim((string)($value ?? '')));
    $map = [
        'gegen lokalen stand' => 'local',
        'lokal' => 'local',
        'local' => 'local',
        'gegen git-stand' => 'git',
        'git' => 'git',
        'gegen git stand' => 'git',
        'keine vergleichsbasis' => 'none',
        'none' => 'none',
        'keine' => 'none',
    ];

    if (isset($map[$candidate])) {
        return $map[$candidate];
    }

    return 'local';
}

function normalize_scope(?string $value): string {
    $candidate = strtolower(trim((string)($value ?? '')));
    $map = [
        'voll' => 'full',
        'voller scope' => 'full',
        'full' => 'full',
        'entwicklungs-scope' => 'development',
        'entwicklung' => 'development',
        'entwicklungsscope' => 'development',
        'punktuell' => 'paths',
        'paths' => 'paths',
    ];

    if (isset($map[$candidate])) {
        return $map[$candidate];
    }

    return 'full';
}

function manifest_script_path(): string {
    $path = realpath(__DIR__ . '/../../../scripts/create_delta_package.ps1');
    if ($path === false) {
        throw new RuntimeException('PowerShell-Skript nicht gefunden.');
    }
    return $path;
}

try {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        json_error('Keine Daten erhalten.');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        json_error('JSON-Body ungültig.');
    }

    $compareSource = normalize_compare_source((string)($payload['compare_source'] ?? ''));
    $scope = normalize_scope((string)($payload['scope'] ?? ''));

    if ($compareSource === 'none') {
        json_error('Für ein Manifest muss eine Vergleichsbasis gewählt werden.');
    }

    $script = manifest_script_path();
    $modeMap = [
        'local' => 'Local',
        'git' => 'Git',
    ];
    $mode = $modeMap[$compareSource] ?? 'Local';

    $scriptModeMap = [
        'full' => 'Full',
        'development' => 'Development',
        'paths' => 'Paths',
    ];
    $scriptMode = $scriptModeMap[$scope] ?? 'Full';

    if ($scope === 'paths') {
        json_error('Punktueller Scope erfordert eine manuelle Pfadauswahl. Bitte erst Pfade definieren.');
    }

    $outputDir = realpath(__DIR__ . '/../../../deploy-packages');
    if ($outputDir === false) {
        if (!mkdir(__DIR__ . '/../../../deploy-packages', 0777, true) && !is_dir(__DIR__ . '/../../../deploy-packages')) {
            throw new RuntimeException('deploy-packages Verzeichnis konnte nicht erstellt werden.');
        }
        $outputDir = realpath(__DIR__ . '/../../../deploy-packages');
    }
    if ($outputDir === false) {
        throw new RuntimeException('deploy-packages Verzeichnis konnte nicht ermittelt werden.');
    }

    $command = sprintf(
        'powershell -ExecutionPolicy Bypass -File "%s" -Mode %s -ManifestMode %s -OutputMode ManifestOnly',
        str_replace('\\', '\\\\', $script),
        $scriptMode,
        $mode
    );

    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);

    if ($returnCode !== 0) {
        $message = implode("\n", $output);
        json_error('Manifest-Erzeugung fehlgeschlagen: ' . trim($message !== '' ? $message : 'Unbekannter Fehler'));
    }

    $latestManifest = null;
    $latestTime = 0;
    $iterator = new DirectoryIterator($outputDir);
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'json' || strpos($fileInfo->getFilename(), '.manifest.') === false) {
            continue;
        }
        $mtime = $fileInfo->getMTime();
        if ($mtime > $latestTime) {
            $latestTime = $mtime;
            $latestManifest = $outputDir . DIRECTORY_SEPARATOR . $fileInfo->getFilename();
        }
    }

    if ($latestManifest === null || !is_file($latestManifest)) {
        json_error('Manifest wurde erzeugt, aber die Datei konnte nicht gefunden werden.');
    }

    $filename = basename($latestManifest);
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'filename' => $filename,
        'path' => $latestManifest,
        'compare_source' => $compareSource,
        'scope' => $scope,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}
