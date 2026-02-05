<?php
// scripts/scrape_w3schools.php (Browser-compatible, PHP 7.4)
//
// Open in browser:
//   /scripts/scrape_w3schools.php
//
// Writes output to:
//   storage/help/help.json
//
// IMPORTANT:
// - Keep storage/help out of git
// - This is intended for local use (not public server)

declare(strict_types=1);

const OUT_DIR  = __DIR__ . '/../storage/help';
const OUT_FILE = __DIR__ . '/../storage/help/help.json';

const USER_AGENT = 'pythonIDE-local-help-scraper/1.0 (+local use)';
const TIMEOUT_S  = 20;

// polite delay between requests (ms)
const DELAY_MIN_MS = 250;
const DELAY_MAX_MS = 650;

// -----------------------------
// What we want to scrape
// -----------------------------
$targets = [
  // String methods
  'str.split'       => 'https://www.w3schools.com/python/ref_string_split.asp',
  'str.strip'       => 'https://www.w3schools.com/python/ref_string_strip.asp',
  'str.replace'     => 'https://www.w3schools.com/python/ref_string_replace.asp',
  'str.splitlines'  => 'https://www.w3schools.com/python/ref_string_splitlines.asp',
  'str.startswith'  => 'https://www.w3schools.com/python/ref_string_startswith.asp',
  'str.endswith'    => 'https://www.w3schools.com/python/ref_string_endswith.asp',
  'str.find'        => 'https://www.w3schools.com/python/ref_string_find.asp',
  'str.join'        => 'https://www.w3schools.com/python/ref_string_join.asp',
  'str.upper'       => 'https://www.w3schools.com/python/ref_string_upper.asp',
  'str.lower'       => 'https://www.w3schools.com/python/ref_string_lower.asp',
  'str.format'      => 'https://www.w3schools.com/python/ref_string_format.asp',
    // more string methods
'str.capitalize' => 'https://www.w3schools.com/python/ref_string_capitalize.asp',
'str.title'      => 'https://www.w3schools.com/python/ref_string_title.asp',
'str.swapcase'   => 'https://www.w3schools.com/python/ref_string_swapcase.asp',
'str.casefold'   => 'https://www.w3schools.com/python/ref_string_casefold.asp',
'str.lstrip'     => 'https://www.w3schools.com/python/ref_string_lstrip.asp',
'str.rstrip'     => 'https://www.w3schools.com/python/ref_string_rstrip.asp',
'str.center'     => 'https://www.w3schools.com/python/ref_string_center.asp',
'str.ljust'      => 'https://www.w3schools.com/python/ref_string_ljust.asp',
'str.rjust'      => 'https://www.w3schools.com/python/ref_string_rjust.asp',
'str.zfill'      => 'https://www.w3schools.com/python/ref_string_zfill.asp',
'str.count'      => 'https://www.w3schools.com/python/ref_string_count.asp',
'str.index'      => 'https://www.w3schools.com/python/ref_string_index.asp',
'str.rindex'     => 'https://www.w3schools.com/python/ref_string_rindex.asp',
'str.rfind'      => 'https://www.w3schools.com/python/ref_string_rfind.asp',
'str.partition'  => 'https://www.w3schools.com/python/ref_string_partition.asp',
'str.rpartition' => 'https://www.w3schools.com/python/ref_string_rpartition.asp',
'str.rsplit'     => 'https://www.w3schools.com/python/ref_string_rsplit.asp',


  // List methods
  'list.append'     => 'https://www.w3schools.com/python/ref_list_append.asp',
  'list.extend'     => 'https://www.w3schools.com/python/ref_list_extend.asp',
  'list.insert'     => 'https://www.w3schools.com/python/ref_list_insert.asp',
  'list.pop'        => 'https://www.w3schools.com/python/ref_list_pop.asp',
  'list.remove'     => 'https://www.w3schools.com/python/ref_list_remove.asp',
  'list.sort'       => 'https://www.w3schools.com/python/ref_list_sort.asp',
  'list.reverse'    => 'https://www.w3schools.com/python/ref_list_reverse.asp',
  'list.clear'      => 'https://www.w3schools.com/python/ref_list_clear.asp',

  // Dict methods
  'dict.get'        => 'https://www.w3schools.com/python/ref_dictionary_get.asp',
  'dict.keys'       => 'https://www.w3schools.com/python/ref_dictionary_keys.asp',
  'dict.values'     => 'https://www.w3schools.com/python/ref_dictionary_values.asp',
  'dict.items'      => 'https://www.w3schools.com/python/ref_dictionary_items.asp',
  'dict.update'     => 'https://www.w3schools.com/python/ref_dictionary_update.asp',
  'dict.pop'        => 'https://www.w3schools.com/python/ref_dictionary_pop.asp',
  'dict.setdefault' => 'https://www.w3schools.com/python/ref_dictionary_setdefault.asp',
  'dict.clear'      => 'https://www.w3schools.com/python/ref_dictionary_clear.asp',
];

// -----------------------------
// HTML helpers
// -----------------------------
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

// -----------------------------
// FS helpers
// -----------------------------
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

// -----------------------------
// HTTP
// -----------------------------
function httpGet(string $url): string {
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
  $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($body === false) throw new RuntimeException("HTTP GET failed: $url :: $err");
  if ($code >= 400)     throw new RuntimeException("HTTP $code for $url");
  return (string)$body;
}

// -----------------------------
// Parsing
// -----------------------------
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
  if (mb_strlen($defText) > 320) {
    $defText = mb_substr($defText, 0, 320) . '…';
  }

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

// -----------------------------
// Run
// -----------------------------
htmlHeader();

try {
  ensureDir(OUT_DIR);
} catch (Throwable $e) {
  logLine("ERROR creating output dir: " . $e->getMessage(), "err");
  htmlFooter();
  exit;
}

// optional: allow ?force=1 to re-scrape
$force = isset($_GET['force']) && $_GET['force'] === '1';

if (!$force && is_file(OUT_FILE)) {
  logLine("Hinweis: help.json existiert bereits. Mit ?force=1 neu erzeugen.", "warn");
}

$total = count($targets);
$i = 0;

$result = [];

foreach ($targets as $key => $url) {
  $i++;
  logLine("[$i/$total] Fetch: $key → $url");

  try {
    $html = httpGet($url);
    $entry = parseW3Reference($html, $key, $url);
    $result[$key] = $entry;
    logLine("  OK: " . ($entry['title'] ?? $key), "ok");
  } catch (Throwable $e) {
    logLine("  ERROR: " . $e->getMessage(), "err");
  }

  $delay = randDelayMs();
  logLine("  sleep ${delay}ms");
  usleep($delay * 1000);
}

$json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
  logLine("ERROR: json_encode failed", "err");
  htmlFooter();
  exit;
}

$ok = @file_put_contents(OUT_FILE, $json);
if ($ok === false) {
  logLine("ERROR: Failed to write " . OUT_FILE, "err");
  htmlFooter();
  exit;
}

logLine("");
logLine("DONE ✅", "ok");
logLine("Wrote: " . OUT_FILE, "ok");
logLine("Entries: " . count($result) . " / $total", "ok");
logLine("Tipp: in index.php nutzt du dann ?api=help&key=str.split");

htmlFooter();
