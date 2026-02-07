<?php
// scripts/scrape_w3schools.php (Browser-compatible, PHP 7.4)
//
// Run in browser:
//   /scripts/scrape_w3schools.php?force=1
//
// Writes:
//   storage/help/help.json
//
// Strategy:
// - Build a big target list from method/function name lists
// - Generate W3Schools reference URLs
// - Fetch + parse if page exists (HTTP < 400), otherwise SKIP
//
// Types supported:
// - str.<method>  -> ref_string_<method>.asp
// - list.<method> -> ref_list_<method>.asp
// - dict.<method> -> ref_dictionary_<method>.asp
// - set.<method>  -> ref_set_<method>.asp
// - tuple.<method>-> ref_tuple_<method>.asp
// - math.<func>   -> ref_math_<func>.asp  (best-effort, many exist)
// - builtins.<fn> -> ref_func_<fn>.asp or ref_func_<fn>_function.asp (best-effort)
//
// Missing pages are skipped.

declare(strict_types=1);

const OUT_DIR  = __DIR__ . '/../storage/help';
const OUT_FILE = __DIR__ . '/../storage/help/help.json';

const USER_AGENT = 'pythonIDE-local-help-scraper/1.1 (+local use)';
const TIMEOUT_S  = 20;

const DELAY_MIN_MS = 200;
const DELAY_MAX_MS = 550;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function htmlHeader(): void {
  echo "<!doctype html><html lang='de'><head><meta charset='utf-8'>";
  echo "<meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<title>Scrape W3Schools → Local Help</title>";
  echo "<style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:18px}
    .box{border:1px solid #e5e7eb;border-radius:10px;padding:14px}
    .log{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;white-space:pre-wrap}
    .ok{color:#16a34a;font-weight:700}
    .warn{color:#b45309;font-weight:700}
    .err{color:#dc2626;font-weight:700}
    .meta{color:#6b7280;font-size:13px}
    code{background:#f3f4f6;padding:2px 4px;border-radius:6px}
  </style></head><body>";
  echo "<h2>W3Schools Scraper (lokal)</h2>";
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

function randDelayMs(): int {
  return random_int(DELAY_MIN_MS, DELAY_MAX_MS);
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

function parseW3Reference(string $html, string $fallbackTitle, string $url): array {
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->loadHTML($html);
  $xp = new DOMXPath($dom);

  $h1 = trim((string)$xp->evaluate('string(//h1[1])'));
  $title = $h1 !== '' ? $h1 : $fallbackTitle;

  $syntax = '';
  $syntaxNode = $xp->query('//h2[normalize-space()="Syntax"]')->item(0);
  if ($syntaxNode) {
    $syn = trim((string)$xp->evaluate('string(following::div[contains(@class,"w3-code")][1])', $syntaxNode));
    if ($syn !== '') $syntax = preg_replace("/\s+/", " ", $syn);
  }

  $defText = '';
  $defNode = $xp->query('//h2[contains(normalize-space(),"Definition") and contains(normalize-space(),"Usage")]')->item(0);
  if ($defNode) {
    $p = trim((string)$xp->evaluate('string(following::p[1])', $defNode));
    if ($p !== '') $defText = $p;
  } else {
    $p = trim((string)$xp->evaluate('string(//p[1])'));
    if ($p !== '') $defText = $p;
  }

  $code = '';
  $codeNodes = $xp->query('//div[contains(@class,"w3-code")]');
  if ($codeNodes) {
    foreach ($codeNodes as $node) {
      $txt = trim($node->textContent);
      if ($txt === '') continue;
      if (preg_match('/\bprint\b|=|\.\w+\(/', $txt)) {
        $code = $txt;
        break;
      }
    }
  }

  if ($code !== '') {
    $lines = preg_split("/\R/u", $code);
    $lines = array_slice($lines, 0, 14);
    $code = rtrim(implode("\n", $lines));
  }

  $defText = trim(preg_replace("/\s+/", " ", $defText));
  if (mb_strlen($defText) > 320) $defText = mb_substr($defText, 0, 320) . '…';

  $md = "**{$title}**\n\n";
  if ($defText !== '') $md .= $defText . "\n\n";
  if ($syntax !== '')  $md .= "**Syntax**\n\n`{$syntax}`\n\n";
  if ($code !== '')    $md .= "**Example**\n\n```python\n{$code}\n```\n\n";
  $md .= "Source: {$url}";

  return [
    'title' => $title,
    'md' => $md,
    'source' => $url,
    'fetched_at' => gmdate('c'),
  ];
}

/**
 * Extract module symbols (e.g. np.array, plt.plot) from code examples on a topic page.
 * Returns an array of symbol names (without module prefix), e.g. ['array','reshape']
 */
function extractModuleSymbols(string $html, string $module): array {
  libxml_use_internal_errors(true);
  $dom = new DOMDocument();
  $dom->loadHTML($html);
  $xp = new DOMXPath($dom);

  $codeNodes = $xp->query('//div[contains(@class,"w3-code")]');
  $found = [];
  if ($codeNodes) {
    foreach ($codeNodes as $node) {
      $txt = $node->textContent;
      if (!$txt) continue;

      if ($module === 'np') {
        if (preg_match_all('/\b(?:np|numpy)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $txt, $m)) {
          foreach ($m[1] as $sym) $found[$sym] = true;
        }
      } elseif ($module === 'plt') {
        if (preg_match_all('/\bplt\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $txt, $m)) {
          foreach ($m[1] as $sym) $found[$sym] = true;
        }
      }
    }
  }

  // Fallback: also search the whole page text for patterns like "numpy.array(" or "np.array("
  if (empty($found)) {
    if ($module === 'np') {
      if (preg_match_all('/\b(?:np|numpy)\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $html, $m)) {
        foreach ($m[1] as $sym) $found[$sym] = true;
      }
    } elseif ($module === 'plt') {
      if (preg_match_all('/\bplt\.([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $html, $m)) {
        foreach ($m[1] as $sym) $found[$sym] = true;
      }
    }
  }

  return array_keys($found);
}
function addTargets(array &$targets, string $prefix, array $names, callable $urlFn): void {
  foreach ($names as $name) {
    $name = trim((string)$name);
    if ($name === '') continue;
    $targets["{$prefix}.{$name}"] = $urlFn($name);
  }
}

/** Learn what NumPy/Matplotlib topics W3Schools has, extract topic URLs from an index page */
function discoverTopicUrls(string $indexUrl, string $type): array {
  try {
    $code = 0;
    $html = httpGet($indexUrl, $code);
    if ($code >= 400) return [];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xp = new DOMXPath($dom);

    // Look for all <a href> that match the pattern /{type}_<topic>.asp
    $pattern = '/' . preg_quote($type, '/') . '_[a-z0-9_]+\.asp$/i';
    $aElements = $xp->query('//a[@href]');
    
    $urls = [];
    if ($aElements) {
      foreach ($aElements as $a) {
        $href = (string)($a->getAttribute('href') ?? '');
        if (preg_match($pattern, $href)) {
          $fullUrl = 'https://www.w3schools.com/python/' . ltrim($href, '/');
          $urls[$fullUrl] = true;
        }
      }
    }

    return array_keys($urls);
  } catch (Throwable $e) {
    return [];
  }
}

function buildTargets(): array {
  $targets = [];

  // Keep these aligned with your JS completion lists
  $stringMethods = [
    "lower","upper","title","capitalize","swapcase","casefold",
    "strip","lstrip","rstrip","removeprefix","removesuffix",
    "ljust","rjust","center","zfill",
    "find","rfind","index","rindex","count","startswith","endswith",
    "isalpha","isalnum","isdigit","isdecimal","isnumeric","isspace",
    "islower","isupper","istitle",
    "split","rsplit","splitlines","join","partition","rpartition",
    "replace","translate","maketrans","format","format_map","encode",
  ];

  $listMethods = ["append","extend","insert","remove","pop","clear","index","count","sort","reverse","copy"];

  $dictMethods = ["get","setdefault","update","pop","popitem","clear","keys","values","items","copy","fromkeys"];

  $setMethods = [
    "add","clear","copy","difference","difference_update","discard",
    "intersection","intersection_update","isdisjoint","issubset","issuperset",
    "pop","remove","symmetric_difference","symmetric_difference_update","union","update"
  ];

  $tupleMethods = ["count","index"];

  $mathFuncs = [
    "ceil","floor","fabs","factorial","fmod","frexp","fsum","gcd","isfinite","isinf","isnan",
    "ldexp","modf","prod","remainder","trunc",
    "exp","expm1","log","log1p","log2","log10","pow","sqrt",
    "acos","asin","atan","atan2","cos","sin","tan","degrees","radians","hypot",
    "copysign","isclose"
  ];

  $builtins = [
    "print","len","range","enumerate","sum","min","max","sorted","reversed",
    "list","dict","set","tuple","str","int","float","bool",
    "abs","all","any","bin","chr","ord","hex","oct","pow","round","zip","map","filter","input"
  ];

  addTargets($targets, "str",  $stringMethods, fn($m) => "https://www.w3schools.com/python/ref_string_{$m}.asp");
  addTargets($targets, "list", $listMethods,   fn($m) => "https://www.w3schools.com/python/ref_list_{$m}.asp");
  addTargets($targets, "dict", $dictMethods,   fn($m) => "https://www.w3schools.com/python/ref_dictionary_{$m}.asp");
  addTargets($targets, "set",  $setMethods,    fn($m) => "https://www.w3schools.com/python/ref_set_{$m}.asp");
  addTargets($targets, "tuple",$tupleMethods,  fn($m) => "https://www.w3schools.com/python/ref_tuple_{$m}.asp");
  addTargets($targets, "math", $mathFuncs,     fn($m) => "https://www.w3schools.com/python/ref_math_{$m}.asp");
  addTargets($targets, "builtins", $builtins, fn($f) => "https://www.w3schools.com/python/ref_func_{$f}.asp");

  // NumPy & Matplotlib: Discover tutorial topics dynamically
  logLine("Discovering NumPy topics from https://www.w3schools.com/python/numpy_intro.asp...");
  $numpyUrls = discoverTopicUrls("https://www.w3schools.com/python/numpy_intro.asp", "numpy");
  foreach ($numpyUrls as $url) {
    $targets["__np_tutorial__" . count($targets)] = $url;
  }
  logLine("  Found " . count($numpyUrls) . " NumPy tutorials");

  logLine("Discovering Matplotlib topics from https://www.w3schools.com/python/matplotlib_intro.asp...");
  $matplotlibUrls = discoverTopicUrls("https://www.w3schools.com/python/matplotlib_intro.asp", "matplotlib");
  foreach ($matplotlibUrls as $url) {
    $targets["__plt_tutorial__" . count($targets)] = $url;
  }
  logLine("  Found " . count($matplotlibUrls) . " Matplotlib tutorials");

  return $targets;
}

htmlHeader();

try {
  ensureDir(OUT_DIR);
} catch (Throwable $e) {
  logLine("ERROR creating output dir: " . $e->getMessage(), "err");
  htmlFooter(); exit;
}

$force = isset($_GET['force']) && $_GET['force'] === '1';
if (!$force && is_file(OUT_FILE)) {
  logLine("Hinweis: help.json existiert bereits. Mit ?force=1 neu erzeugen.", "warn");
}

$targets = buildTargets();
$total = count($targets);
$i = 0;

$result = [];
$skipped = 0;

foreach ($targets as $key => $url) {
  $i++;
  logLine("[$i/$total] Fetch: $key → $url");

  try {
    $code = 0;
    $html = httpGet($url, $code);

    if ($code >= 400) {
      $skipped++;
      logLine("  SKIP (HTTP $code): $key", "warn");
    } else {
      // For NumPy/Matplotlib topics: parse page and also extract specific function names
      if (strpos($key, '__np_tutorial__') === 0) {
        $entry = parseW3Reference($html, $key, $url);
        $symbols = extractModuleSymbols($html, 'np');
        if (!empty($symbols)) {
          foreach ($symbols as $sym) {
            $symKey = "np.$sym";
            if (!isset($result[$symKey])) {
              $result[$symKey] = $entry;
              logLine("  OK (mapped): $symKey -> $url", "ok");
            }
          }
        } else {
          // fallback: map topic name to single function key
          if (preg_match('/numpy_([a-z0-9_]+)\.asp$/i', $url, $m)) {
            $topicName = $m[1];
            $parts = explode('_', $topicName);
            $funcName = $parts[0];
            $symKey = "np.$funcName";
            if (!isset($result[$symKey])) {
              $result[$symKey] = $entry;
              logLine("  OK (topic fallback): $symKey -> $url", "ok");
            }
          }
        }
      } elseif (strpos($key, '__plt_tutorial__') === 0) {
        $entry = parseW3Reference($html, $key, $url);
        $symbols = extractModuleSymbols($html, 'plt');
        if (!empty($symbols)) {
          foreach ($symbols as $sym) {
            $symKey = "plt.$sym";
            if (!isset($result[$symKey])) {
              $result[$symKey] = $entry;
              logLine("  OK (mapped): $symKey -> $url", "ok");
            }
          }
        } else {
          if (preg_match('/matplotlib_([a-z0-9_]+)\.asp$/i', $url, $m)) {
            $funcName = $m[1];
            $symKey = "plt.$funcName";
            if (!isset($result[$symKey])) {
              $result[$symKey] = $entry;
              logLine("  OK (topic fallback): $symKey -> $url", "ok");
            }
          }
        }
      } else {
        $entry = parseW3Reference($html, $key, $url);
        $result[$key] = $entry;
        logLine("  OK: " . ($entry['title'] ?? $key), "ok");
      }
    }
  } catch (Throwable $e) {
    $skipped++;
    logLine("  SKIP (error): " . $e->getMessage(), "warn");
  }

  usleep(randDelayMs() * 1000);
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
  logLine("ERROR: json_encode failed", "err");
  htmlFooter(); exit;
}

if (@file_put_contents(OUT_FILE, $json) === false) {
  logLine("ERROR: Failed to write " . OUT_FILE, "err");
  htmlFooter(); exit;
}

logLine("");
logLine("DONE ✅", "ok");
logLine("Wrote: " . OUT_FILE, "ok");
logLine("Entries saved: " . count($result) . " / $total", "ok");
logLine("Skipped: $skipped (no ref page / error)", "warn");
logLine("Test: /public/index.php?api=help&key=set.add", "ok");
logLine("Test: /public/index.php?api=help&key=math.sqrt", "ok");
logLine("Test: /public/index.php?api=help&key=builtins.print", "ok");

htmlFooter();
