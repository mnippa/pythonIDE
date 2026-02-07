<?php
$testUrls = [
    'https://www.geeksforgeeks.org/python/numpy-sin-python/',
    'https://www.geeksforgeeks.org/python/numpy-array-python/',
    'https://www.geeksforgeeks.org/python/numpy-ones_like-python/',
    'https://www.geeksforgeeks.org/numpy/python-numpy/',
    'https://www.geeksforgeeks.org/python/matplotlib-plot-python/',
    '/python/numpy-sin-python/',
    '/python/numpy-array/',
];

foreach ($testUrls as $url) {
    $match = preg_match('/\/python\/numpy-[a-z0-9_\-]+/i', $url);
    $match2 = preg_match('/\/python\/matplotlib-[a-z0-9_\-]+/i', $url);
    echo sprintf("%s -> numpy: %d, matplotlib: %d\n", $url, $match, $match2);
}
