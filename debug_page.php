<?php
function httpGet(string $url, int &$httpCode = 0): string {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => 'pythonIDE-geeksforgeeks-scraper/2.0 (+local use)',
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_ENCODING => 'gzip, deflate',
  ]);

  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($body === false) throw new RuntimeException("HTTP GET failed: $url :: $err");
  return (string)$body;
}

// Fetch NumPy page
$npListingUrl = 'https://www.geeksforgeeks.org/numpy/python-numpy/';
echo "Fetching: $npListingUrl\n";
$html = httpGet($npListingUrl);
echo "Downloaded " . strlen($html) . " bytes\n";

// Parse with DOMDocument
libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xp = new DOMXPath($dom);

// Find all links with numpy in href
$aElements = $xp->query('//a[@href]');
echo "Total <a> elements found: " . ($aElements ? $aElements->length : 0) . "\n";

// Show links matching our pattern (same as scraper)
$numpyCount = 0;
if ($aElements) {
  foreach ($aElements as $a) {
    $href = (string)($a->getAttribute('href') ?? '');
    // Same pattern as scraper: /python/numpy-(?!python-)...
    if (preg_match('/\/python\/numpy-(?!python-)([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $href)) {
      echo "MATCHED: $href\n";
      $numpyCount++;
      if ($numpyCount >= 10) break;
    }
  }
}
echo "Matched NumPy links (with negative lookahead): $numpyCount\n";
