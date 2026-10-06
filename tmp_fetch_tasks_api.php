<?php
$url = 'http://localhost/pythonIDE/api/tasks/list.php?assignment_id=35';
$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Cookie: PHPSESSID={$_COOKIE['PHPSESSID']}\r\n"
    ]
]);
$data = @file_get_contents($url, false, $ctx);
echo $data === false ? "FETCH_FAILED" : $data;
