<?php
/**
 * Database configuration and connection
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'start123');
define('DB_NAME', 'pythonide');

function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'Database connection failed']));
        }
        
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
