<?php

declare(strict_types=1);

require_once __DIR__ . '/../../auth/middleware.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function starts_with(string $value, string $needle): bool {
    if ($needle === '') {
        return true;
    }
    return substr($value, 0, strlen($needle)) === $needle;
}

function repo_root(): string {
    $root = realpath(__DIR__ . '/../../..');
    if ($root === false) {
        throw new RuntimeException('Repository root konnte nicht ermittelt werden.');
    }
    return $root;
}

function normalize_relative_path(string $path): string {
    $candidate = str_replace('\\', '/', trim($path));
    while (starts_with($candidate, './')) {
        $candidate = substr($candidate, 2);
    }
    if ($candidate === '' || starts_with($candidate, '/') || preg_match('~(^|/)\.\.(?:/|$)~', $candidate)) {
        throw new RuntimeException('Ungultiger relativer Pfad: ' . $path);
    }
    return $candidate;
}

function is_deployable_path(string $relativePath): bool {
    $allowedDirectories = ['api', 'public', 'config', 'components'];
    $allowedRootFiles = ['.htaccess', 'index.php', 'composer.json', 'composer.lock', 'web.config'];
    $protectedFiles = [
        'config/database.php',
        'config/database.beta_live.local.php',
        'config/local.php',
    ];

    if (in_array($relativePath, $protectedFiles, true)) {
        return false;
    }
    if (preg_match('~^config/.*\.local\.php$~', $relativePath)) {
        return false;
    }
    if (preg_match('~^config/database(?:[._-].*)?\.php$~', $relativePath)) {
        return false;
    }
    if (in_array($relativePath, $allowedRootFiles, true)) {
        return true;
    }
    $topLevel = explode('/', $relativePath, 2)[0];
    return in_array($topLevel, $allowedDirectories, true);
}

function collect_current_files(string $root): array {
    $paths = [];
    foreach (['api', 'public', 'config', 'components'] as $directory) {
        $directoryPath = $root . DIRECTORY_SEPARATOR . $directory;
        if (!is_dir($directoryPath)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $relativePath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($root) + 1));
            if (is_deployable_path($relativePath)) {
                $paths[$relativePath] = $fileInfo->getPathname();
            }
        }
    }

    foreach (['.htaccess', 'index.php', 'composer.json', 'composer.lock', 'web.config'] as $rootFile) {
        $filePath = $root . DIRECTORY_SEPARATOR . $rootFile;
        if (is_file($filePath)) {
            $paths[$rootFile] = $filePath;
        }
    }

    ksort($paths);
    return $paths;
}

function file_hash(string $path): string {
    $hash = hash_file('sha256', $path);
    if ($hash === false) {
        throw new RuntimeException('Hash konnte nicht berechnet werden: ' . $path);
    }
    return strtolower($hash);
}

function read_manifest_from_upload(array $upload): array {
    $name = strtolower((string)($upload['name'] ?? ''));
    $extension = pathinfo($name, PATHINFO_EXTENSION);

    if ($extension === 'zip') {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZIP extension is not available.');
        }
        $zip = new ZipArchive();
        if ($zip->open((string)$upload['tmp_name']) !== true) {
            throw new RuntimeException('Could not open ZIP package.');
        }

        try {
            $manifestRaw = $zip->getFromName('manifest.json');
            if (!is_string($manifestRaw) || trim($manifestRaw) === '') {
                return [
                    'available' => false,
                    'message' => 'Das ZIP-Paket enthaelt kein manifest.json. Dadurch ist keine Vergleichspruefung moeglich.',
                ];
            }

            $manifest = json_decode($manifestRaw, true);
            if (!is_array($manifest)) {
                throw new RuntimeException('manifest.json is not valid JSON.');
            }

            return [
                'available' => true,
                'manifest' => $manifest,
                'source' => 'zip',
            ];
        } finally {
            $zip->close();
        }
    }

    $raw = file_get_contents((string)$upload['tmp_name']);
    if ($raw === false || trim($raw) === '') {
        throw new RuntimeException('JSON-Datei ist leer.');
    }

    $manifest = json_decode($raw, true);
    if (!is_array($manifest)) {
        throw new RuntimeException('JSON-Datei konnte nicht gelesen werden.');
    }

    return [
        'available' => true,
        'manifest' => $manifest,
        'source' => 'json',
    ];
}

try {
    $root = repo_root();

    $upload = $_FILES['package'] ?? $_FILES['state_file'] ?? null;
    if (!is_array($upload)) {
        json_response(['ok' => false, 'error' => 'Bitte ein ZIP-Paket oder eine JSON-Stand-Datei hochladen.'], 400);
    }

    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_response(['ok' => false, 'error' => 'Upload fehlgeschlagen.'], 400);
    }
    if ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 50 * 1024 * 1024) {
        json_response(['ok' => false, 'error' => 'Datei muss zwischen 1 Byte und 50 MB gross sein.'], 400);
    }

    $manifestResult = read_manifest_from_upload($upload);
    if (($manifestResult['available'] ?? true) === false) {
        json_response([
            'ok' => true,
            'available' => false,
            'message' => (string)($manifestResult['message'] ?? 'Kein Manifest vorhanden.'),
            'files' => [],
            'notes' => [],
        ]);
    }

    $state = $manifestResult['manifest'] ?? null;
    if (!is_array($state)) {
        json_response(['ok' => false, 'error' => 'Manifest konnte nicht gelesen werden.'], 400);
    }

    $files = $state['files'] ?? null;
    if (!is_array($files) || $files === []) {
        json_response(['ok' => false, 'error' => 'Manifest enthaelt keine Datei-Liste.'], 400);
    }

    $stateId = isset($state['package_id']) ? (string)$state['package_id'] : (isset($state['state_id']) ? (string)$state['state_id'] : null);
    $stateName = $stateId !== null && $stateId !== '' ? $stateId : (isset($state['label']) ? (string)$state['label'] : 'Manifest');
    $manifestMode = isset($state['manifest_mode']) ? (string)$state['manifest_mode'] : (isset($state['source']['mode']) ? (string)$state['source']['mode'] : null);
    $manifestScope = isset($state['scope']) ? (string)$state['scope'] : ($manifestMode !== null ? (string)$manifestMode : 'unknown');
    $excludedPaths = is_array($state['excluded_paths'] ?? null) ? array_values($state['excluded_paths']) : [];

    $currentFiles = collect_current_files($root);
    $currentSet = array_fill_keys(array_keys($currentFiles), true);

    $reportFiles = [];
    $matched = 0;
    $different = 0;
    $missing = 0;
    $invalid = 0;
    $notes = [];

    foreach ($files as $entry) {
        if (!is_array($entry)) {
            $invalid++;
            $notes[] = 'Ein Eintrag war kein Objekt und wurde uebersprungen.';
            continue;
        }

        $path = isset($entry['path']) ? normalize_relative_path((string)$entry['path']) : '';
        if ($path === '' || !is_deployable_path($path)) {
            $invalid++;
            $notes[] = 'Nicht ausrollbarer oder leerer Pfad wurde uebersprungen: ' . ($path !== '' ? $path : '[leer]');
            continue;
        }

        $expectedHash = isset($entry['sha256']) ? strtolower(trim((string)$entry['sha256'])) : '';
        $expectedSize = isset($entry['size']) ? (int)$entry['size'] : null;
        $currentPath = $currentFiles[$path] ?? null;

        if ($currentPath === null || !is_file($currentPath)) {
            $missing++;
            $reportFiles[] = [
                'status' => 'missing',
                'path' => $path,
                'expected_size' => $expectedSize,
                'expected_sha256' => $expectedHash !== '' ? $expectedHash : null,
            ];
            continue;
        }

        $currentHash = file_hash($currentPath);
        $currentSize = filesize($currentPath);
        $isMatch = ($expectedHash !== '' && hash_equals($expectedHash, $currentHash))
            || ($expectedHash === '' && $expectedSize !== null && (int)$expectedSize === (int)$currentSize);

        if ($isMatch) {
            $matched++;
            $reportFiles[] = [
                'status' => 'match',
                'path' => $path,
                'current_size' => $currentSize,
                'current_sha256' => $currentHash,
            ];
            continue;
        }

        $different++;
        $reportFiles[] = [
            'status' => 'different',
            'path' => $path,
            'expected_size' => $expectedSize,
            'expected_sha256' => $expectedHash !== '' ? $expectedHash : null,
            'current_size' => $currentSize,
            'current_sha256' => $currentHash,
        ];
    }

    $statePaths = [];
    foreach ($files as $entry) {
        if (!is_array($entry) || !isset($entry['path'])) {
            continue;
        }
        try {
            $statePath = normalize_relative_path((string)$entry['path']);
            if (is_deployable_path($statePath)) {
                $statePaths[$statePath] = true;
            }
        } catch (Throwable $ignore) {
            // invalid entries already counted above
        }
    }

    $extra = 0;
    foreach ($currentSet as $path => $_value) {
        if (!isset($statePaths[$path])) {
            $extra++;
            $reportFiles[] = [
                'status' => 'extra',
                'path' => $path,
                'current_size' => is_file($currentFiles[$path]) ? filesize($currentFiles[$path]) : null,
                'current_sha256' => is_file($currentFiles[$path]) ? file_hash($currentFiles[$path]) : null,
            ];
        }
    }

    json_response([
        'ok' => true,
        'available' => true,
        'manifest_mode' => $manifestMode,
        'manifest_scope' => $manifestScope,
        'excluded_paths' => $excludedPaths,
        'state_id' => $stateId,
        'state_name' => $stateName,
        'total_files' => $matched + $different + $missing,
        'matched_files' => $matched,
        'different_files' => $different,
        'missing_files' => $missing,
        'extra_files' => $extra,
        'invalid_files' => $invalid,
        'files' => $reportFiles,
        'notes' => $notes,
    ]);
} catch (Throwable $e) {
    json_response([
        'ok' => false,
        'error' => $e->getMessage(),
    ], 500);
}
