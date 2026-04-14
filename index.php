<?php
declare(strict_types=1);

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$target = ($basePath === '' ? '' : $basePath) . '/public/index.php';

header('Location: ' . $target, true, 302);
exit;
