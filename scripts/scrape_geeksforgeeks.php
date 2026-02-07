<?php
// scripts/scrape_geeksforgeeks.php
// Scrapes NumPy and Matplotlib docs from GeeksforGeeks main listing pages
//
// Run in browser:
//   /scripts/scrape_geeksforgeeks.php?force=1
//
// Fetches from:
//   https://www.geeksforgeeks.org/numpy/python-numpy/ (NumPy functions)
//   https://www.geeksforgeeks.org/matplotlib/python-matplotlib/ (Matplotlib functions)

declare(strict_types=1);

const OUT_DIR  = __DIR__ . '/../storage/help';
const OUT_FILE = __DIR__ . '/../storage/help/help.json';

$logDebug = []; // Global debug array to store sample links

const USER_AGENT = 'pythonIDE-geeksforgeeks-scraper/2.0 (+local use)';
const TIMEOUT_S  = 20;
const DELAY_MIN_MS = 300;
const DELAY_MAX_MS = 800;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function htmlHeader(): void {
  echo "<!doctype html><html lang='de'><head><meta charset='utf-8'>";
  echo "<meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<title>GeeksforGeeks Scraper v2</title>";
  echo "<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:18px}
    .box{border:1px solid #e5e7eb;border-radius:10px;padding:14px}
    .log{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;white-space:pre-wrap;max-height:700px;overflow-y:auto}
    .ok{color:#16a34a;font-weight:700}
    .warn{color:#b45309;font-weight:700}
    .err{color:#dc2626;font-weight:700}
    .meta{color:#6b7280;font-size:13px}
    code{background:#f3f4f6;padding:2px 4px;border-radius:6px}
  </style></head><body>";
  echo "<h2>GeeksforGeeks Scraper v2</h2>";
  echo "<div class='meta'>Output: <code>" . h(OUT_FILE) . "</code></div>";
  echo "<div class='box log' id='log'>";
  @ob_flush(); @flush();
}

function htmlFooter(): void {
  echo "</div></body></html>";
  @ob_flush(); @flush();
}

function logLine(string $msg, string $cls = ''): void {
  $prefix = $cls ? "<span class='".h($cls)."'>" : "";
  $suffix = $cls ? "</span>" : "";
  echo $prefix . h($msg) . $suffix . "\n";
  @ob_flush(); @flush();
}

function ensureDir(string $dir): void {
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
      throw new RuntimeException("Failed to create dir: $dir");
    }
  }
}

function httpGet(string $url, int &$httpCode = 0): string {
  $ch = curl_init($url);
  if ($ch === false) throw new RuntimeException("curl_init failed");

  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => TIMEOUT_S,
    CURLOPT_TIMEOUT        => TIMEOUT_S,
    CURLOPT_USERAGENT      => USER_AGENT,
    CURLOPT_HTTPHEADER     => [
      'Accept: text/html,application/xhtml+xml',
      'Accept-Language: en-US,en;q=0.8,de;q=0.6',
    ],
  ]);

  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($body === false) throw new RuntimeException("HTTP GET failed: $url :: $err");
  return (string)$body;
}

/**
 * Parse a GeeksforGeeks article for title and first code example
 */
function parseGeeksforGeeksArticle(string $html, string $fallbackTitle, string $url): array {
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
  $xp = new DOMXPath($dom);

  // Try to get title from <h1> or <title>
  $h1 = trim((string)$xp->evaluate('string(//h1[1])'));
  $title = $h1 !== '' ? $h1 : $fallbackTitle;

  // Extract description: first <p> with reasonable text
  $description = '';
  $pNodes = $xp->query('//p');
  if ($pNodes && $pNodes->length > 0) {
    for ($i = 0; $i < $pNodes->length; $i++) {
      $txt = trim((string)$pNodes->item($i)->textContent);
      if (strlen($txt) > 20 && strlen($txt) < 500 && !preg_match('/^(Advertisement|Share|Report)/i', $txt)) {
        $description = preg_replace('/\s+/', ' ', $txt);
        if (strlen($description) > 250) {
          $description = substr($description, 0, 250) . '…';
        }
        break;
      }
    }
  }

  // Extract first code block
  $code = '';
  $preNodes = $xp->query('//pre | //code[@class="language-python"]');
  if ($preNodes && $preNodes->length > 0) {
    for ($i = 0; $i < $preNodes->length; $i++) {
      $txt = trim($preNodes->item($i)->textContent);
      if (strlen($txt) > 10 && !preg_match('/^(python|Output:|>>>)/i', substr($txt, 0, 20))) {
        $code = $txt;
        // Keep only first 20 lines
        $lines = explode("\n", $code);
        if (count($lines) > 20) {
          $lines = array_slice($lines, 0, 20);
          $code = implode("\n", $lines);
        }
        break;
      }
    }
  }

  $md = "**{$title}**\n\n";
  if ($description !== '') $md .= $description . "\n\n";
  if ($code !== '') $md .= "**Example**\n\n```python\n{$code}\n```\n\n";
  $md .= "Source: {$url}";

  // Ensure UTF-8 encoding
  $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
  $md = mb_convert_encoding($md, 'UTF-8', 'UTF-8');

  return [
    'title' => $title,
    'md' => $md,
    'source' => $url,
    'fetched_at' => gmdate('c'),
  ];
}

/**
 * Extract function URLs from a GeeksforGeeks listing page
 * Flex pattern to match variation like /python/numpy-../, /numpy/.., etc
 */
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
          $fullUrl = preg_match('|^https?://|', $href) 
            ? $href 
            : 'https://www.geeksforgeeks.org' . $href;
          $urls[$fullUrl] = true;
        }
      }
      // For Matplotlib: match URLs with /python/matplotlib-<name> pattern
      elseif ($module === 'plt') {
        if (preg_match('/\/python\/matplotlib-([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $href)) {
          $fullUrl = preg_match('|^https?://|', $href) 
            ? $href 
            : 'https://www.geeksforgeeks.org' . $href;
          $urls[$fullUrl] = true;
        }
      }
    }
  }

  // If zero URLs found, log some sample links for debugging
  if (count($urls) === 0 && count($allLinks) > 0) {
    $GLOBALS['logDebug'][$module] = array_splice($allLinks, 0, 10);
  }

  // Return unique URLs (array_keys to get the URLs from the associative array)
  return array_keys($urls);
}

/**
 * Extract function name from GeeksforGeeks URL
 * Matches forms like /python/numpy-array-python/, /numpy-sin/, /python/matplotlib-plot-python/, etc.
 */
function extractFunctionName(string $url, string $module): ?string {
  if ($module === 'np') {
    // Match /python/numpy-<name>(-python)? and extract <name>
    // Also try /numpy-<name> for compatibility
    if (preg_match('/\/(?:python\/)?numpy-([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $url, $m)) {
      $name = str_replace('-', '_', $m[1]);
      return $name;
    }
  } elseif ($module === 'plt') {
    // Match /python/matplotlib-<name>(-python)? or /matplotlib-<name> and extract <name>
    if (preg_match('/\/(?:python\/)?matplotlib-([a-z0-9_]+?)(?:-python)?(?:\/|#|$)/i', $url, $m)) {
      $name = str_replace('-', '_', $m[1]);
      return $name;
    }
  }
  return null;
}

try {
  htmlHeader();

  ensureDir(OUT_DIR);
  logLine("Starting scrape (GeeksforGeeks v2)...");

  $result = [];
  $count = 0;

  // Scrape NumPy from main listing
  logLine("\n=== NumPy Functions ===");
  $npListingUrl = 'https://www.geeksforgeeks.org/numpy/python-numpy/';
  logLine("Fetching: {$npListingUrl}");

  try {
    $code = 0;
    $html = httpGet($npListingUrl, $code);
    if ($code >= 400) {
      logLine("Failed to fetch listing (HTTP $code)", 'err');
    } else {
      $npUrls = extractFunctionUrlsFromListing($html, 'np');
      logLine("Found " . count($npUrls) . " NumPy function links", ($npUrls ? 'ok' : 'warn'));
      
      // Debug: show a sample of the URLs
      if (count($npUrls) > 0 && count($npUrls) < 20) {
        logLine("  (Debug) Found URLs:", 'warn');
        foreach (array_slice($npUrls, 0, 3) as $url) {
          logLine("    - " . substr($url, 0, 80), 'warn');
        }
      }
      
      if (count($npUrls) === 0 && isset($GLOBALS['logDebug']['np'])) {
        logLine("  (Debug) Sample of all links with 'numpy':", 'warn');
        foreach ($GLOBALS['logDebug']['np'] as $link) {
          logLine("    - " . $link, 'warn');
        }
      }

      foreach ($npUrls as $url) {
        $funcName = extractFunctionName($url, 'np');
        if (!$funcName) continue;

        $key = "np.{$funcName}";
        logLine("  → $key");

        try {
          usleep(random_int(DELAY_MIN_MS * 1000, DELAY_MAX_MS * 1000));
          $code = 0;
          $html = httpGet($url, $code);
          if ($code >= 400) {
            logLine("    (HTTP $code, skipped)", 'warn');
            continue;
          }

          $entry = parseGeeksforGeeksArticle($html, "numpy.{$funcName}", $url);
          $result[$key] = $entry;
          $count++;
          logLine("    ✓", 'ok');
        } catch (Exception $e) {
          logLine("    ✗ " . $e->getMessage(), 'err');
        }
      }
    }
  } catch (Exception $e) {
    logLine("Error fetching NumPy listing: " . $e->getMessage(), 'err');
  }

  // Scrape Matplotlib from main listing
  logLine("\n=== Matplotlib Functions ===");
  $pltListingUrl = 'https://www.geeksforgeeks.org/matplotlib/python-matplotlib/';
  logLine("Fetching: {$pltListingUrl}");

  try {
    $code = 0;
    $html = httpGet($pltListingUrl, $code);
    if ($code >= 400) {
      logLine("Failed to fetch listing (HTTP $code)", 'err');
    } else {
      $pltUrls = extractFunctionUrlsFromListing($html, 'plt');
      logLine("Found " . count($pltUrls) . " Matplotlib function links", ($pltUrls ? 'ok' : 'warn'));

      if (count($pltUrls) === 0 && isset($GLOBALS['logDebug']['plt'])) {
        logLine("  (Debug) Sample of all links with 'matplotlib':", 'warn');
        foreach ($GLOBALS['logDebug']['plt'] as $link) {
          logLine("    - " . $link, 'warn');
        }
      }

      foreach ($pltUrls as $url) {
        $funcName = extractFunctionName($url, 'plt');
        if (!$funcName) continue;

        $key = "plt.{$funcName}";
        logLine("  → $key");

        try {
          usleep(random_int(DELAY_MIN_MS * 1000, DELAY_MAX_MS * 1000));
          $code = 0;
          $html = httpGet($url, $code);
          if ($code >= 400) {
            logLine("    (HTTP $code, skipped)", 'warn');
            continue;
          }

          $entry = parseGeeksforGeeksArticle($html, "matplotlib.{$funcName}", $url);
          $result[$key] = $entry;
          $count++;
          logLine("    ✓", 'ok');
        } catch (Exception $e) {
          logLine("    ✗ " . $e->getMessage(), 'err');
        }
      }
    }
  } catch (Exception $e) {
    logLine("Error fetching Matplotlib listing: " . $e->getMessage(), 'err');
  }

  // Save results
  logLine("\n=== Saving ===");
  $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  if ($json === false) {
    throw new RuntimeException("json_encode failed: " . json_last_error_msg());
  }

  if (file_put_contents(OUT_FILE, $json, LOCK_EX) === false) {
    throw new RuntimeException("Failed to write: " . OUT_FILE);
  }

  logLine("Wrote " . count($result) . " help entries to: " . OUT_FILE, 'ok');
  logLine("NumPy: " . count(array_filter(array_keys($result), fn($k) => strpos($k, 'np.') === 0)) . " functions", 'ok');
  logLine("Matplotlib: " . count(array_filter(array_keys($result), fn($k) => strpos($k, 'plt.') === 0)) . " functions", 'ok');
  logLine("\nDone! ✓", 'ok');

} catch (Exception $e) {
  logLine("Fatal error: " . $e->getMessage(), 'err');
} finally {
  htmlFooter();
}
?>

