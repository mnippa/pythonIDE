<?php

declare(strict_types=1);

function adminDeployHistoryFilePath(?string $appRoot = null): string
{
    $root = $appRoot ?? dirname(__DIR__);
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin-deploy-history.json';
}

function adminUiVersionFilePath(?string $appRoot = null): string
{
    $root = $appRoot ?? dirname(__DIR__);
    return rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'admin-ui-version.json';
}

function readAdminDeployHistory(?string $appRoot = null): array
{
    $default = [
        'entries' => [],
        'next_id' => 1,
    ];

    $historyPath = adminDeployHistoryFilePath($appRoot);
    if (is_file($historyPath)) {
        $raw = file_get_contents($historyPath);
        if ($raw !== false && trim($raw) !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $entries = [];
                foreach (($data['entries'] ?? []) as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $entries[] = [
                        'id' => max(1, (int)($entry['id'] ?? count($entries) + 1)),
                        'kind' => (string)($entry['kind'] ?? 'deployment'),
                        'label' => (string)($entry['label'] ?? ($entry['kind'] ?? 'deployment')),
                        'target' => isset($entry['target']) ? (string)$entry['target'] : null,
                        'package_id' => isset($entry['package_id']) ? (string)$entry['package_id'] : null,
                        'created_at' => (string)($entry['created_at'] ?? date(DATE_ATOM)),
                        'actor' => is_array($entry['actor'] ?? null) ? [
                            'id' => $entry['actor']['id'] ?? null,
                            'email' => $entry['actor']['email'] ?? null,
                        ] : null,
                        'details' => is_array($entry['details'] ?? null) ? $entry['details'] : null,
                    ];
                }

                return [
                    'entries' => $entries,
                    'next_id' => count($entries) + 1,
                ];
            }
        }
    }

    $summaryPath = adminUiVersionFilePath($appRoot);
    if (!is_file($summaryPath)) {
        return $default;
    }

    $raw = file_get_contents($summaryPath);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $default;
    }

    return [
        'entries' => [[
            'id' => max(1, (int)($data['version'] ?? 1)),
            'kind' => (string)($data['kind'] ?? 'legacy-summary'),
            'label' => (string)($data['reason'] ?? 'Legacy Stand'),
            'target' => isset($data['target']) ? (string)$data['target'] : null,
            'package_id' => isset($data['package_id']) ? (string)$data['package_id'] : null,
            'created_at' => isset($data['updated_at']) ? (string)$data['updated_at'] : date(DATE_ATOM),
            'actor' => is_array($data['actor'] ?? null) ? [
                'id' => $data['actor']['id'] ?? null,
                'email' => $data['actor']['email'] ?? null,
            ] : null,
            'details' => null,
        ]],
        'next_id' => max(2, (int)($data['version'] ?? 1) + 1),
    ];
}

function readAdminUiVersion(?string $appRoot = null): array
{
    $history = readAdminDeployHistory($appRoot);
    $entries = $history['entries'] ?? [];
    $latest = $entries ? $entries[array_key_last($entries)] : null;

    if (is_array($latest)) {
        return [
            'version' => max(1, (int)($latest['id'] ?? 1)),
            'updated_at' => isset($latest['created_at']) ? (string)$latest['created_at'] : null,
            'reason' => isset($latest['label']) ? (string)$latest['label'] : null,
            'kind' => isset($latest['kind']) ? (string)$latest['kind'] : null,
            'target' => isset($latest['target']) ? (string)$latest['target'] : null,
            'package_id' => isset($latest['package_id']) ? (string)$latest['package_id'] : null,
            'actor' => is_array($latest['actor'] ?? null) ? $latest['actor'] : null,
        ];
    }

    return [
        'version' => 1,
        'updated_at' => null,
        'reason' => null,
        'kind' => null,
        'target' => null,
        'package_id' => null,
        'actor' => null,
    ];
}

function appendAdminDeployHistory(?string $appRoot = null, array $entry = []): array
{
    $history = readAdminDeployHistory($appRoot);
    $entries = $history['entries'] ?? [];
    $nextId = (int)($history['next_id'] ?? (count($entries) + 1));

    $normalizedEntry = [
        'id' => $nextId,
        'kind' => (string)($entry['kind'] ?? 'deployment'),
        'label' => (string)($entry['label'] ?? ($entry['kind'] ?? 'deployment')),
        'target' => isset($entry['target']) ? (string)$entry['target'] : null,
        'package_id' => isset($entry['package_id']) ? (string)$entry['package_id'] : null,
        'created_at' => date(DATE_ATOM),
        'actor' => is_array($entry['actor'] ?? null) ? [
            'id' => $entry['actor']['id'] ?? null,
            'email' => $entry['actor']['email'] ?? null,
        ] : null,
        'details' => is_array($entry['details'] ?? null) ? $entry['details'] : null,
    ];

    $entries[] = $normalizedEntry;
    $payload = [
        'entries' => $entries,
        'next_id' => $nextId + 1,
    ];

    $historyPath = adminDeployHistoryFilePath($appRoot);
    $historyDir = dirname($historyPath);
    if (!is_dir($historyDir) && !mkdir($historyDir, 0775, true) && !is_dir($historyDir)) {
        throw new RuntimeException('Could not create admin deploy history directory.');
    }

    $historyJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($historyJson === false || file_put_contents($historyPath, $historyJson . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write admin deploy history file.');
    }

    $summary = [
        'version' => $normalizedEntry['id'],
        'updated_at' => $normalizedEntry['created_at'],
        'reason' => $normalizedEntry['label'],
        'kind' => $normalizedEntry['kind'],
        'target' => $normalizedEntry['target'],
        'package_id' => $normalizedEntry['package_id'],
        'actor' => $normalizedEntry['actor'],
    ];

    $summaryJson = json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($summaryJson === false || file_put_contents(adminUiVersionFilePath($appRoot), $summaryJson . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write admin version summary file.');
    }

    return $normalizedEntry;
}

function bumpAdminUiVersion(?string $appRoot = null, string $reason = '', ?array $actor = null): array
{
    return appendAdminDeployHistory($appRoot, [
        'kind' => 'deployment',
        'label' => $reason !== '' ? $reason : 'deployment',
        'actor' => $actor,
    ]);
}

function adminDeployHistorySummary(?string $appRoot = null): array
{
    $history = readAdminDeployHistory($appRoot);
    $entries = $history['entries'] ?? [];
    $latest = $entries ? $entries[array_key_last($entries)] : null;

    return [
        'latest' => $latest,
        'entries' => $entries,
        'count' => count($entries),
    ];
}
