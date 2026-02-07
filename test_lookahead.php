<?php
echo "Testing negative lookahead for numpy-python patterns:\n";
$urls = [
    'https://www.geeksforgeeks.org/python/numpy-python-set-2-advanced/',
    'https://www.geeksforgeeks.org/python/numpy-sin-python/',
    'https://www.geeksforgeeks.org/python/numpy-array-python/',
    '/python/numpy-python-set/',
];

foreach ($urls as $url) {
    $match = preg_match('/\/python\/numpy-(?!python-)([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $url);
    echo "$url -> " . ($match ? "MATCH" : "no match") . "\n";
}
