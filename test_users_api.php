<?php
// Test users list API
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require __DIR__ . '/api/admin/users/list.php';
