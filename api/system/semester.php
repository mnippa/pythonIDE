<?php
/**
 * Semester Information API
 * GET /api/system/semester - Get current semester and info
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/semester.php';

header('Content-Type: application/json');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$action = $_GET['action'] ?? 'current';

try {
    $conn = getDbConnection();
    
    if ($action === 'current') {
        // Get current semester
        $current = getCurrentSemester();
        
        jsonResponse([
            'ok' => true,
            'semester' => $current,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else if ($action === 'calculate') {
        // Calculate semester from date
        $date = $_GET['date'] ?? null;
        
        if (!$date) {
            jsonResponse(['ok' => false, 'error' => 'Date parameter required'], 400);
        }
        
        if (!strtotime($date)) {
            jsonResponse(['ok' => false, 'error' => 'Invalid date format'], 400);
        }
        
        $semester = calculateSemester($date);
        
        jsonResponse([
            'ok' => true,
            'date' => $date,
            'semester' => $semester
        ]);
        
    } else if ($action === 'list') {
        // List all semesters with user counts
        $result = $conn->query("
            SELECT 
                COUNT(*) as count,
                CASE 
                    WHEN MONTH(registration_date) >= 3 AND MONTH(registration_date) <= 9
                    THEN CONCAT('SoSe', YEAR(registration_date) % 100)
                    WHEN MONTH(registration_date) >= 10
                    THEN CONCAT('WiSe', YEAR(registration_date) % 100, (YEAR(registration_date) + 1) % 100)
                    ELSE CONCAT('WiSe', (YEAR(registration_date) - 1) % 100, YEAR(registration_date) % 100)
                END as semester
            FROM users
            WHERE status = 'aktiv'
            GROUP BY semester
            ORDER BY registration_date DESC
        ");
        
        $semesters = [];
        while ($row = $result->fetch_assoc()) {
            $semesters[] = $row;
        }
        
        jsonResponse([
            'ok' => true,
            'semesters' => $semesters,
            'current' => getCurrentSemester()
        ]);
        
    } else if ($action === 'info') {
        // Get semester calculation logic info
        jsonResponse([
            'ok' => true,
            'rules' => [
                'sommersemester' => [
                    'name' => 'SoSe',
                    'months' => '03-09',
                    'start' => '1. März',
                    'end' => '30. September',
                    'format' => 'SoSe{YY}'
                ],
                'wintersemester' => [
                    'name' => 'WiSe',
                    'months' => '10-02 (nächstes Jahr)',
                    'start' => '1. Oktober',
                    'end' => '28./29. Februar',
                    'format' => 'WiSe{YY}{YY+1}'
                ]
            ],
            'examples' => [
                '2026-02-08' => calculateSemester('2026-02-08'),
                '2026-03-15' => calculateSemester('2026-03-15'),
                '2026-10-01' => calculateSemester('2026-10-01'),
                '2027-02-28' => calculateSemester('2027-02-28')
            ]
        ]);
        
    } else {
        jsonResponse(['ok' => false, 'error' => 'Unknown action: ' . $action], 400);
    }
    
} catch (Exception $e) {
    jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
