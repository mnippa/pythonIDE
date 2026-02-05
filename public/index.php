<?php
// public/index.php
declare(strict_types=1);

/**
 * API: Local help store
 *   /index.php?api=help&key=str.split
 */
function jsonResponse(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if (isset($_GET['api']) && $_GET['api'] === 'help') {
  $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
  if ($key === '') jsonResponse(['ok' => false, 'error' => 'missing key'], 400);

  $storeFile = __DIR__ . '/../storage/help/help.json';
  if (!is_file($storeFile)) {
    jsonResponse([
      'ok' => false,
      'error' => 'help store not found',
      'hint' => 'Run scraper: /scripts/scrape_w3schools.php?force=1',
    ], 404);
  }

  $raw = file_get_contents($storeFile);
  if ($raw === false) jsonResponse(['ok' => false, 'error' => 'failed to read help store'], 500);

  $data = json_decode($raw, true);
  if (!is_array($data)) jsonResponse(['ok' => false, 'error' => 'invalid help store json'], 500);

  if (!isset($data[$key])) jsonResponse(['ok' => false, 'found' => false], 404);

  $e = $data[$key];
  jsonResponse([
    'ok' => true,
    'found' => true,
    'key' => $key,
    'title' => $e['title'] ?? $key,
    'md' => $e['md'] ?? '',
    'source' => $e['source'] ?? null,
    'fetched_at' => $e['fetched_at'] ?? null,
  ]);
}

?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Python IDE</title>

  <style>
    :root {
      --border:#e5e7eb;
      --muted:#6b7280;
      --bg:#fff;
      --panel:#f9fafb;
    }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); }

    .toolbar{
      display:flex; gap:10px; align-items:center;
      padding:10px; border-bottom:1px solid var(--border);
    }
    .toolbar button{ padding:8px 12px; cursor:pointer; }

    /* Overall layout: TOP main + BOTTOM lint/help */
    .shell{
      height: calc(100vh - 52px);
      display:grid;
      grid-template-rows: 1fr 180px;
      min-height:0;
    }

    .main{
      display:grid;
      grid-template-columns: 1fr 1fr;
      min-height:0;
      min-width:0;
    }

    .left{
      border-right:1px solid var(--border);
      min-width:0; min-height:0;
    }
    #editor-container{ width:100%; height:100%; }

    .right{
      display:grid;
      grid-template-rows: 1fr 1fr;
      min-width:0; min-height:0;
    }
    #output-container{
      padding:10px;
      overflow:auto;
      background:#fff;
      border-bottom:1px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-height:0;
    }
    #plot-container{
      padding:10px;
      overflow:auto;
      background:#fff;
      min-height:0;
    }

    /* Bottom split: 40% lint | 60% help */
    .bottom{
      display:grid;
      grid-template-columns: 40% 60%;
      border-top:1px solid var(--border);
      min-height:0;
      min-width:0;
    }

    #lint-container{
      border-right:1px solid var(--border);
      background:var(--panel);
      padding:10px;
      overflow:auto;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-height:0;
      min-width:0;
    }

    #help-container{
      padding:10px;
      overflow:auto;
      background:#fff;
      font-size:14px;
      line-height:1.35;
      min-height:0;
      min-width:0;
    }
    #help-container .help-muted{ color: var(--muted); }
    #help-container pre{
      background:#0b1020; color:#e5e7eb;
      padding:10px; border-radius:10px; overflow:auto;
    }
    #help-container code{
      background:#f3f4f6; padding:2px 4px; border-radius:6px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 0.95em;
    }

    /* Optional: plot cards if your plot renderer uses these */
    .plot-card{ border:1px solid var(--border); border-radius:12px; margin-bottom:10px; overflow:hidden; }
    .plot-card-header{ padding:8px 10px; background:#f3f4f6; font-weight:700; border-bottom:1px solid var(--border); }
    .plot-img{ width:100%; height:auto; display:block; }
  </style>

  <!-- Monaco loader (AMD) -->
  <script src="monaco/min/vs/loader.js"></script>
  <script>
    require.config({ paths: { vs: "monaco/min/vs" } });
  </script>

  <!-- Pyodide -->
  <script src="pyodide/pyodide.js"></script>

  <!-- Your IDE setup (keeps your current repo logic) -->
  <script type="module" src="js/editor-setup.js"></script>
</head>

<body>
  <div class="toolbar">
    <button id="run-btn">Run</button>
    <span style="opacity:.7">Unten: 40% Lint | 60% Hilfe</span>
  </div>

  <div class="shell">
    <!-- TOP -->
    <div class="main">
      <div class="left">
        <div id="editor-container"></div>
      </div>

      <div class="right">
        <div id="output-container"></div>
        <div id="plot-container"></div>
      </div>
    </div>

    <!-- BOTTOM -->
    <div class="bottom">
      <div id="lint-container"></div>
      <div id="help-container">
        <div class="help-muted">
          Hover auf Methodenname (z.B. <code>split</code>, <code>append</code>, <code>get</code>) → Hilfe erscheint hier.
          <br><br>
          Tipp: <code>s = "a,b"</code> + <code>s.split(",")</code> und dann über <code>split</code> hovern.
        </div>
      </div>
    </div>
  </div>
</body>
</html>
