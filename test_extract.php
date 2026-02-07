<?php
// Test extractFunctionUrlsFromListing directly

function extractFunctionUrlsFromListing(string $html, string $module): array {
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
  $xp = new DOMXPath($dom);

  $urls = [];
  $allLinks = [];
  
  $aElements = $xp->query('//a[@href]');
  if ($aElements) {
    foreach ($aElements as $a) {
      $href = (string)($a->getAttribute('href') ?? '');
      if (!$href) continue;

      // Normalize: ensure it's a full URL or starts with /
      if (!preg_match('|^https?://|', $href) && !preg_match('|^/|', $href)) {
        continue; // Skip relative paths
      }

      // Store all links for debugging
      $allLinks[] = substr($href, 0, 100);

      // For NumPy: match URLs with /python/numpy-<name>-python/ pattern  
      if ($module === 'np') {
        // Match /python/numpy-<name> where <name> is not "python-..." (negative lookahead)
        // This filters out pages like "numpy-python-set-2-advanced"
        if (preg_match('/\/python\/numpy-(?!python-)([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $href)) {
          echo "MATCH: $href\n";
          $fullUrl = preg_match('|^https?://|', $href) 
            ? $href 
            : 'https://www.geeksforgeeks.org' . $href;
          $urls[$fullUrl] = true;
        }
      }
    }
  }

  return array_keys($urls);
}

// Fetch and test
function httpGet(string $url): string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'Test',
  ]);
  $body = curl_exec($ch);
  curl_close($ch);
  return (string)$body;
}

$html = httpGet('https://www.geeksforgeeks.org/numpy/python-numpy/');
$urls = extractFunctionUrlsFromListing($html, 'np');
echo "\nTotal URLs returned: " . count($urls) . "\n";
foreach ($urls as $url) {
  echo "- " . substr($url, 0, 80) . "\n";
}
