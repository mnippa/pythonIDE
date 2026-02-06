<?php
// public/index.php
declare(strict_types=1);

function jsonResponse(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

// API: local help store
if (isset($_GET['api']) && $_GET['api'] === 'help') {
  $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
  if ($key === '') jsonResponse(['ok' => false, 'error' => 'missing key'], 400);

  $storeFile = __DIR__ . '/../storage/help/help.json';
  if (!is_file($storeFile)) jsonResponse(['ok' => false, 'found' => false], 404);

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
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Python IDE</title>

  <style>
    :root {
      --border:#e5e7eb; --muted:#6b7280; --bg:#fff; --panel:#f9fafb;
      --text-primary: #1f2937;
      --text-secondary: #6b7280;
      --code-bg: #f3f4f6;
      --code-color: #1f2937;
      --inline-code-bg: #e5e7eb;
      --help-bg: #ffffff;
      --help-text: #1f2937;
    }
    
    html.dark-mode {
      --border:#374151; --muted:#9ca3af; --bg:#1e1e1e; --panel:#252526;
      --text-primary: #e6edf3;
      --text-secondary: #8b949e;
      --code-bg: #0d1117;
      --code-color: #e6edf3;
      --inline-code-bg: #161b22;
      --help-bg: #1e1e1e;
      --help-text: #e6edf3;
    }
    
    *{ box-sizing:border-box; }
    body{ margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:var(--bg); color:var(--text-primary); transition:background 0.2s, color 0.2s; }

    .toolbar{
      display:flex; gap:12px; align-items:center; flex-wrap:wrap;
      padding:10px; border-bottom:1px solid var(--border);
      background:var(--bg);
    }
    .toolbar button{ padding:8px 12px; cursor:pointer; background:var(--panel); color:var(--text-primary); border:1px solid var(--border); border-radius:4px; transition:background 0.2s; }
    .toolbar button:hover{ background:var(--text-secondary); opacity:0.7; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }
    .toolcheck{ display:flex; gap:6px; align-items:center; padding:6px 10px; border:1px solid var(--border); border-radius:999px; background:var(--panel); color:var(--text-primary); }
    .toolcheck input{ transform: translateY(0.5px); }

    /* MASTER GRID: 75% left / 25% right */
    .app{
      height: calc(100vh - 52px);
      display:grid;
      grid-template-columns: 75% 25%;
      min-height:0;
      min-width:0;
    }

    /* LEFT COLUMN: editor top, bottom tools (lint+help) */
    .left{
      border-right:1px solid var(--border);
      display:grid;
      grid-template-rows: 1fr 180px;  /* bottom only under editor */
      min-width:0; min-height:0;
    }
    #editor-container{ width:100%; height:100%; min-width:0; min-height:0; }

    .left-bottom{
      border-top:1px solid var(--border);
      display:grid;
      grid-template-columns: 40% 60%;
      min-width:0; min-height:0;
    }
    #lint-container{
      border-right:1px solid var(--border);
      background:var(--bg);
      color:var(--text-primary);
      padding:10px;
      overflow:auto;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-width:0; min-height:0;
    }
    #help-container{
      padding:10px;
      overflow:auto;
      background:var(--help-bg);
      color:var(--help-text);
      font-size:14px;
      line-height:1.6;
      min-width:0; min-height:0;
    }
    #help-container .help-muted{ color:var(--text-secondary); }
    #help-container pre{
      background:var(--code-bg);
      color:var(--code-color);
      padding:12px;
      border-radius:6px;
      overflow-x:auto;
      margin:10px 0;
      border-left:3px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:0.9em;
      line-height:1.5;
    }
    #help-container code{
      background:var(--inline-code-bg);
      color:var(--code-color);
      padding:2px 6px;
      border-radius:4px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:0.9em;
    }
    #help-container strong{
      color:var(--text-primary);
      font-weight:600;
    }
    #help-container a{
      color:#3b82f6;
      text-decoration:none;
    }
    #help-container a:hover{
      text-decoration:underline;
    }

    /* RIGHT COLUMN: output top + plot bottom (full height) */
    .right{
      display:grid;
      grid-template-rows: 1fr 1fr;
      min-width:0; min-height:0;
    }
    #output-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      border-bottom:1px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:13px;
      white-space:pre-wrap;
      min-width:0; min-height:0;
    }
    #plot-container{
      padding:10px;
      overflow:auto;
      background:var(--bg);
      color:var(--text-primary);
      min-width:0; min-height:0;
    }

    .plot-card{ border:1px solid var(--border); border-radius:12px; margin-bottom:10px; overflow:hidden; }
    .plot-card-header{ padding:8px 10px; background:var(--panel); color:var(--text-primary); font-weight:700; border-bottom:1px solid var(--border); }
    .plot-img{ width:100%; height:auto; display:block; }
  </style>

  <!-- Monaco loader (AMD) -->
  <script src="monaco/min/vs/loader.js"></script>
  <script>
    require.config({ paths: { vs: "monaco/min/vs" } });
  </script>

  <!-- Pyodide -->
  <script src="pyodide/pyodide.js"></script>

  <script type="module" src="js/editor-setup.js"></script>
</head>

<body>
  <div class="toolbar">
    <button id="run-btn">Run</button>

    <label class="toolcheck" title="NumPy laden">
      <input id="pkg-numpy" type="checkbox" checked>
      <span>NumPy</span>
    </label>

    <label class="toolcheck" title="Matplotlib laden">
      <input id="pkg-matplotlib" type="checkbox" checked>
      <span>Matplotlib</span>
    </label>

    <span style="opacity:.7">Links: Editor + Lint/Hilfe | Rechts: Output + Plot</span>
    
    <div style="flex:1"></div>
    <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
  </div>

  <div class="app">
    <div class="left">
      <div id="editor-container"></div>

      <div class="left-bottom">
        <div id="lint-container"></div>
        <div id="help-container">
          <div class="help-muted">
            Hilfe erscheint hier:
            <br>• Cursor auf <code>var.method</code> (z.B. <code>s.split</code>)
            <br>• auch beim Navigieren in Autovorschlägen (↑/↓)
            <br><br>
            Wenn ein Eintrag fehlt: Scraper erneut laufen lassen (<code>?force=1</code>).
          </div>
        </div>
      </div>
    </div>

    <div class="right">
      <div id="output-container"></div>
      <div id="plot-container"></div>
    </div>
  </div>

  <script>
    // Theme Toggle
    (function() {
      const html = document.documentElement;
      const themeBtn = document.getElementById('theme-toggle');
      
      // Load saved theme from localStorage
      const savedTheme = localStorage.getItem('theme') || 'light';
      if (savedTheme === 'dark') {
        html.classList.add('dark-mode');
      }
      
      // Toggle theme
      themeBtn?.addEventListener('click', () => {
        html.classList.toggle('dark-mode');
        const isDark = html.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
      });
    })();
  </script>
</body>
</html>
