<?php
/**
 * User Logout API
 */

require_once __DIR__ . '/../../config/database.php';

session_start();
header('Content-Type: application/json');

// Clear session
session_unset();
session_destroy();

jsonResponse(['ok' => true, 'message' => 'Logged out successfully']);
