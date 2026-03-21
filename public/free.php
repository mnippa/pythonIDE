<?php
// public/free.php
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
  $storeFile = __DIR__ . '/../storage/help/help.json';
  if (!is_file($storeFile)) jsonResponse(['ok' => false, 'found' => false], 404);

  $raw = file_get_contents($storeFile);
  if ($raw === false) jsonResponse(['ok' => false, 'error' => 'failed to read help store'], 500);

  $data = json_decode($raw, true);
  if (!is_array($data)) jsonResponse(['ok' => false, 'error' => 'invalid help store json'], 500);

  // Special API: return all keys (for dynamic completion loading)
  $key = isset($_GET['key']) ? trim((string)$_GET['key']) : '';
  if ($key === '__list__' || $key === '') {
    jsonResponse(['ok' => true, 'keys' => array_keys($data)]);
  }

  if ($key === '') jsonResponse(['ok' => false, 'error' => 'missing key'], 400);

  // Try direct lookup first
  if (isset($data[$key])) {
    $e = $data[$key];
    $resolved = $key;
  } else {
    // Fallbacks: if key contains module prefix like "plt.plot" or "np.array",
    // try the bare name "plot" / "array" and common module expansions.
    $resolved = null;
    $candidates = [$key];
    if (strpos($key, '.') !== false) {
      [$mod, $name] = explode('.', $key, 2) + [null, null];
      if ($name) {
        $candidates[] = $name; // bare function name
        if ($mod === 'plt') $candidates[] = 'matplotlib.' . $name;
        if ($mod === 'np')  $candidates[] = 'numpy.' . $name;
      }
    }

    foreach ($candidates as $cand) {
      if (isset($data[$cand])) {
        $e = $data[$cand];
        $resolved = $cand;
        break;
      }
    }

    if ($resolved === null) {
      // Fallback: static help for common math and string functions
      $fallbackHelp = [
        'math.ceil' => [
          'title' => 'math.ceil(x)',
          'md' => 'Returns the ceiling of x, the smallest integer >= x.\n\n```python\nimport math\nmath.ceil(4.3)  # 5\nmath.ceil(-2.5)  # -2\n```'
        ],
        'math.floor' => [
          'title' => 'math.floor(x)',
          'md' => 'Returns the floor of x, the largest integer <= x.\n\n```python\nimport math\nmath.floor(4.7)  # 4\nmath.floor(-2.5)  # -3\n```'
        ],
        'math.sqrt' => [
          'title' => 'math.sqrt(x)',
          'md' => 'Returns the square root of x.\n\n```python\nimport math\nmath.sqrt(16)  # 4.0\nmath.sqrt(2)   # 1.414...\n```'
        ],
        'math.sin' => [
          'title' => 'math.sin(x)',
          'md' => 'Returns the sine of x (in radians).\n\n```python\nimport math\nmath.sin(math.pi/2)  # 1.0\nmath.sin(0)          # 0.0\n```'
        ],
        'math.cos' => [
          'title' => 'math.cos(x)',
          'md' => 'Returns the cosine of x (in radians).\n\n```python\nimport math\nmath.cos(0)           # 1.0\nmath.cos(math.pi)     # -1.0\n```'
        ],
        'math.tan' => [
          'title' => 'math.tan(x)',
          'md' => 'Returns the tangent of x (in radians).\n\n```python\nimport math\nmath.tan(math.pi/4)  # 1.0\nmath.tan(0)          # 0.0\n```'
        ],
        'math.pi' => [
          'title' => 'math.pi',
          'md' => 'The mathematical constant π (pi), approximately 3.14159.\n\n```python\nimport math\nmath.pi  # 3.14159265...\n```'
        ],
        'math.e' => [
          'title' => 'math.e',
          'md' => 'The mathematical constant e (Euler\'s number), approximately 2.71828.\n\n```python\nimport math\nmath.e  # 2.718281828...\n```'
        ],
        'str.split' => [
          'title' => 'str.split(sep=None, maxsplit=-1)',
          'md' => 'Splits the string by separator and returns a list.\n\n```python\n"hello world".split()      # [\'hello\', \'world\']\n"a,b,c".split(",")         # [\'a\', \'b\', \'c\']\n"a:b:c".split(":", 1)      # [\'a\', \'b:c\']\n```'
        ],
        'str.join' => [
          'title' => 'str.join(iterable)',
          'md' => 'Concatenates an iterable of strings with this string as separator.\n\n```python\n",".join([\'a\', \'b\', \'c\'])     # \'a,b,c\'\n" ".join(["hello", "world"])  # \'hello world\'\n```'
        ],
        'str.replace' => [
          'title' => 'str.replace(old, new, count=-1)',
          'md' => 'Returns a copy with all occurrences of old replaced with new.\n\n```python\n"hello world".replace("world", "Python")  # \'hello Python\'\n"aaa".replace("a", "b", 2)                   # \'bba\'\n```'
        ],
        'str.strip' => [
          'title' => 'str.strip(chars=None)',
          'md' => 'Returns a copy with leading and trailing whitespace removed.\n\n```python\n"  hello  ".strip()     # \'hello\'\n"xxxhelloxxx".strip("x")  # \'hello\'\n```'
        ],
        'str.upper' => [
          'title' => 'str.upper()',
          'md' => 'Returns a copy converted to uppercase.\n\n```python\n"hello".upper()  # \'HELLO\'\n```'
        ],
        'str.lower' => [
          'title' => 'str.lower()',
          'md' => 'Returns a copy converted to lowercase.\n\n```python\n"HELLO".lower()  # \'hello\'\n```'
        ],
        'str.startswith' => [
          'title' => 'str.startswith(prefix, start=0, end=len(string))',
          'md' => 'Returns True if the string starts with prefix.\n\n```python\n"hello".startswith("he")   # True\n"hello".startswith("lo")   # False\n```'
        ],
        'str.endswith' => [
          'title' => 'str.endswith(suffix, start=0, end=len(string))',
          'md' => 'Returns True if the string ends with suffix.\n\n```python\n"hello".endswith("lo")  # True\n"hello".endswith("he")  # False\n```'
        ],
        'str.find' => [
          'title' => 'str.find(sub, start=0, end=len(string))',
          'md' => 'Returns the lowest index where substring is found, or -1 if not found.\n\n```python\n"hello".find("l")   # 2\n"hello".find("x")   # -1\n```'
        ],
        'str.format' => [
          'title' => 'str.format(*args, **kwargs)',
          'md' => 'Formats this string using positional and keyword arguments.\n\n```python\n"Hello, {}!".format("world")           # \'Hello, world!\'\n"x={x}, y={y}".format(x=1, y=2)       # \'x=1, y=2\'\n```'
        ],
      ];

      if (isset($fallbackHelp[$key])) {
        $fallback = $fallbackHelp[$key];
        jsonResponse([
          'ok' => true,
          'found' => true,
          'requested_key' => $key,
          'resolved_key' => $key,
          'title' => $fallback['title'],
          'md' => $fallback['md'],
          'source' => 'fallback',
          'note' => 'This help is provided as a fallback while the help database is being extended.'
        ]);
      }

      jsonResponse(['ok' => false, 'found' => false], 404);
    }
  }
  jsonResponse([
    'ok' => true,
    'found' => true,
    'requested_key' => $key,
    'resolved_key' => $resolved ?? $key,
    'title' => $e['title'] ?? ($resolved ?? $key),
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
  <title>Python IDE - Free Editor</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <link rel="stylesheet" href="css/ide.css">
  <link rel="stylesheet" href="css/file-tree.css">

  <style>
    :root {
      --border:#e5e7eb; --muted:#6b7280; --bg:#fff; --panel:#f9fafb;
      --text-primary:#1f2937; --text-secondary:#6b7280;
      --code-bg:#f3f4f6; --code-color:#1f2937; --inline-code-bg:#e5e7eb;
      --help-bg:#ffffff; --help-text:#1f2937;
      --accent:#667eea;
    }
    html.dark-mode {
      --border:#374151; --muted:#9ca3af; --bg:#1e1e1e; --panel:#252526;
      --text-primary:#e6edf3; --text-secondary:#8b949e;
      --code-bg:#0d1117; --code-color:#e6edf3; --inline-code-bg:#161b22;
      --help-bg:#1e1e1e; --help-text:#e6edf3;
    }

    *{box-sizing:border-box}
    html{height:100vh;overflow:hidden}
    body{
      margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;
      background:var(--bg); color:var(--text-primary);
      transition:background .2s,color .2s;
      height:100vh; overflow:hidden;
      display:grid; grid-template-rows:auto 1fr;
    }

    .hspf-header-content{ max-width:none; }
    html.dark-mode .hspf-header{ background-color:var(--panel); border-bottom-color:var(--border); }
    html.dark-mode .hspf-brand-text,html.dark-mode .hspf-page-title{ color:var(--text-primary); }
    html.dark-mode .hspf-divider{ background-color:var(--border); }
    .toolbar{
      display:flex; gap:10px; align-items:center; flex-wrap:wrap;
      padding:3px 10px; background:transparent;
    }
    .toolbar button{
      padding:8px 12px; cursor:pointer; background:var(--panel); color:var(--text-primary);
      border:1px solid var(--border); border-radius:6px; transition:background .2s;
    }
    .toolbar button:hover{ background:var(--text-secondary); opacity:.75; }
    .toolbar .primary{ background:var(--accent); color:#fff; border-color:transparent; opacity:1; }
    .toolbar .primary:hover{ opacity:.9; }
    #settings-toggle{ width:34px; height:34px; padding:0; display:flex; align-items:center; justify-content:center; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; width:20px; height:20px; line-height:20px; display:block; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }

    .toolcheck{ display:flex; gap:6px; align-items:center; padding:6px 10px; border:1px solid var(--border); border-radius:999px; background:var(--panel); color:var(--text-primary); }
    .settings-panel{
      position:fixed; top:52px; right:10px; z-index:120;
      background:var(--panel); color:var(--text-primary);
      border:1px solid var(--border); border-radius:10px; padding:10px;
      min-width:190px; display:none; flex-direction:column; gap:8px;
      box-shadow:0 12px 30px rgba(0,0,0,.12);
    }
    .settings-panel.open{ display:flex; }
    .settings-title{ font-size:12px; text-transform:uppercase; color:var(--text-secondary); }

    .app{
      height:100%;
      display:grid;
      grid-template-columns:1fr 5px 240px;
      min-height:0;
      overflow:hidden;
    }
    .app.with-project-details{
      grid-template-columns:264px 1fr 5px 240px !important;
    }
    @media (min-width:1201px){
      .app{ grid-template-columns:1fr 5px 1fr; }
      .app.with-project-details{ grid-template-columns:440px 1fr 5px 1fr !important; }
    }
    @media (max-width:768px){
      .app{ grid-template-columns:1fr 30%; }
      .app.with-project-details{ grid-template-columns:1fr 30% !important; }
      #project-list-panel{ display:none !important; }
      .column-splitter{ display:none !important; }
    }

    #project-list-panel{ border-right:1px solid var(--border); background:var(--bg); display:flex; flex-direction:column; min-height:0; overflow:hidden; }
    .project-navigation{ border-bottom:2px solid var(--border); background:var(--panel); padding:10px; font-size:12px; color:var(--text-secondary); }
    #file-tree-wrapper{ border-bottom:1px solid var(--border); background:var(--bg); display:flex; flex-direction:column; min-height:220px; flex:1 1 auto; overflow:hidden; }
    #file-tree-wrapper .tree-header{ padding:6px 8px; border-bottom:1px solid var(--border); background:var(--panel); font-weight:600; font-size:12px; color:var(--text-primary); display:flex; align-items:center; justify-content:space-between; }
    .tree-header-actions{ display:flex; gap:3px; }
    .tree-header-actions button{ background:none; border:1px solid var(--border); border-radius:4px; cursor:pointer; padding:2px 5px; font-size:11px; color:var(--text-secondary); }
    .tree-header-actions button:hover{ background:var(--bg); color:var(--text-primary); }
    #project-file-tree{ flex:1; overflow:auto; padding:6px; font-size:13px; }
    .free-tree-item{ display:flex; align-items:center; gap:6px; padding:5px 8px; border-radius:6px; cursor:pointer; }
    .free-tree-item:hover{ background:var(--panel); }
    .free-tree-item.active{ background:rgba(102,126,234,.15); border-left:3px solid var(--accent); }
    .free-folder{ font-weight:600; color:var(--text-primary); cursor:default; }
    .free-tree-name{ flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
    .free-tree-actions{ display:none; gap:1px; flex-shrink:0; align-items:center; }
    .free-tree-item:hover .free-tree-actions{ display:flex; }
    .free-tree-actions button{ background:none; border:none; cursor:pointer; padding:2px 4px; border-radius:4px; font-size:12px; opacity:.65; color:var(--text-primary); }
    .free-tree-actions button:hover{ opacity:1; background:rgba(0,0,0,.1); }
    html.dark-mode .free-tree-actions button:hover{ background:rgba(255,255,255,.12); }
    .project-details-content{ flex:0 0 160px; padding:12px; overflow:auto; font-size:12px; color:var(--text-secondary); }

    .editor-run-bar{ display:flex; align-items:center; padding:5px 12px; gap:10px; background:var(--panel); border-bottom:1px solid var(--border); }
    .editor-filename{ font-size:12px; color:var(--text-secondary); font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #run-btn{ background:#22c55e; color:#fff; border:none; padding:7px 22px; border-radius:6px; font-size:14px; font-weight:700; cursor:pointer; letter-spacing:.3px; box-shadow:0 2px 6px rgba(34,197,94,.35); white-space:nowrap; }
    #run-btn:hover{ background:#16a34a; box-shadow:0 3px 8px rgba(34,197,94,.5); }
    .editor-area{ border-right:1px solid var(--border); display:grid; grid-template-rows:auto 1fr minmax(150px,25%); min-width:0; min-height:0; }
    .editor-container-wrapper{ min-width:0; min-height:0; display:flex; }
    #editor-container{ width:100%; flex:1; min-width:0; min-height:0; }
    .editor-bottom{ border-top:1px solid var(--border); display:grid; grid-template-columns:40% 60%; min-width:0; min-height:0; background:var(--bg); }
    #lint-container{ border-right:1px solid var(--border); background:var(--bg); color:var(--text-primary); padding:10px; overflow:auto; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:13px; white-space:pre-wrap; }
    #help-container{ padding:6px 8px; overflow:auto; background:var(--help-bg); color:var(--help-text); font-size:14px; line-height:1.5; }

    .column-splitter{ width:5px; background:var(--border); cursor:col-resize; position:relative; }
    .column-splitter:hover,.column-splitter.dragging{ background:var(--accent); }

    .right{ display:grid; grid-template-rows:1fr 1fr; min-width:0; min-height:0; }
    #gui-container{ padding:10px; overflow:auto; background:var(--bg); color:var(--text-primary); border-bottom:1px solid var(--border); display:none; }
    #gui-container.active{ display:block; }
    #output-plot-section{ display:grid; grid-template-rows:auto 1fr; min-width:0; min-height:0; }
    #output-plot-tabs{ display:flex; border-bottom:1px solid var(--border); background:var(--panel); }
    .output-plot-tab{ flex:1; padding:10px 12px; border:none; background:var(--panel); color:var(--text-secondary); cursor:pointer; font-size:12px; border-bottom:3px solid transparent; }
    .output-plot-tab.active{ color:var(--accent); border-bottom-color:var(--accent); background:var(--bg); }
    .output-plot-panel{ display:none; }
    .output-plot-panel.active{ display:block; }
    #output-container,#plot-container{ padding:10px; overflow:auto; background:var(--bg); color:var(--text-primary); font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:13px; white-space:pre-wrap; }

    .modal-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; align-items:center; justify-content:center; z-index:2000; }
    .modal-overlay.open{ display:flex; }
    .modal-content{ width:min(520px,92vw); background:var(--bg); color:var(--text-primary); border:1px solid var(--border); border-radius:10px; box-shadow:0 14px 40px rgba(0,0,0,.3); }
    .modal-header{ padding:14px 16px; border-bottom:1px solid var(--border); font-weight:600; }
    .modal-body{ padding:16px; }
    .modal-footer{ padding:12px 16px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
    .modal-footer button{ padding:8px 12px; border-radius:6px; border:1px solid var(--border); background:var(--panel); color:var(--text-primary); cursor:pointer; }
    .modal-footer .primary{ background:var(--accent); color:#fff; border-color:transparent; }
  </style>

  <script src="monaco/min/vs/loader.js"></script>
  <script>require.config({ paths: { vs: 'monaco/min/vs' } });</script>
  <script src="pyodide/pyodide.js"></script>
  <script type="module" src="js/editor-setup.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body>
<?php
  $pageTitle = 'Free Editor';
  $headerActions = <<<HTML
    <div class="toolbar">
      <button id="free-new-empty-btn" class="primary">Neu ohne UI</button>
      <button id="free-new-ui-btn">Neu mit UI</button>
      <button id="free-new-template-btn">Neu aus Vorlage</button>
      <div style="flex:1"></div>
      <button id="download-file-btn" class="icon-btn" title="Aktuelle Datei herunterladen" style="font-size:13px;">⬇ Datei</button>
      <button id="download-zip-btn" class="icon-btn" title="Alle Dateien als ZIP herunterladen" style="font-size:13px;">📦 ZIP</button>
      <a href="login.php" style="padding:8px 16px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">Anmelden / Registrieren</a>
      <button id="settings-toggle" title="Module" aria-label="Module settings">⚙</button>
      <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
    </div>
HTML;
  include(__DIR__ . '/../components/header.php');
?>

  <div id="settings-panel" class="settings-panel" aria-hidden="true">
    <div class="settings-title">Module</div>
    <label class="toolcheck"><input id="pkg-numpy" type="checkbox" checked><span>NumPy</span></label>
    <label class="toolcheck"><input id="pkg-matplotlib" type="checkbox" checked><span>Matplotlib</span></label>
    <label class="toolcheck"><input id="pkg-pandas" type="checkbox"><span>Pandas</span></label>
    <label class="toolcheck"><input id="pkg-panel" type="checkbox" disabled><span>Panel (nicht verfuegbar)</span></label>
    <label class="toolcheck"><input id="pkg-seaborn" type="checkbox" disabled><span>Seaborn (nicht verfuegbar)</span></label>
  </div>

  <div class="app with-project-details" id="editor-view">
    <div id="project-list-panel">
      <div class="project-navigation">
        <div><strong>Free Workspace</strong></div>
        <div>Lokale Datei-Struktur ohne Projektliste.</div>
      </div>
      <div id="file-tree-wrapper">
        <div class="tree-header">📁 Dateien<span class="tree-header-actions"><button data-action="new-file-root" title="Neue Datei im Root">📄+</button><button data-action="new-folder-root" title="Neuer Ordner im Root">📁+</button></span></div>
        <div id="project-file-tree"></div>
      </div>
      <div class="project-details-content" id="free-project-details-content">Noch kein Workspace erstellt.</div>
    </div>

    <div class="editor-area">
      <div class="editor-run-bar">
        <span id="editor-filename-label" class="editor-filename"></span>
        <button id="run-btn">▶ Run</button>
      </div>
      <div class="editor-container-wrapper"><div id="editor-container"></div></div>
      <div class="editor-bottom">
        <div id="lint-container"></div>
        <div id="help-container"></div>
      </div>
    </div>

    <div class="column-splitter" id="column-splitter"></div>

    <div class="right">
      <div id="gui-container"></div>
      <div id="output-plot-section">
        <div id="output-plot-tabs">
          <button class="output-plot-tab active" data-tab="output">📜 Output</button>
          <button class="output-plot-tab" data-tab="plot">📊 Plot</button>
        </div>
        <div id="output-container" class="output-plot-panel active"></div>
        <div id="plot-container" class="output-plot-panel"></div>
      </div>
    </div>
  </div>

  <div id="free-template-modal" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">Neu aus Vorlage</div>
      <div class="modal-body">
        <label for="free-template-select" style="display:block;margin-bottom:8px;font-weight:600;">Vorlage</label>
        <select id="free-template-select" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:6px;background:var(--panel);color:var(--text-primary);">
          <option value="empty_python">Leeres Python Projekt</option>
          <option value="empty_python_html">Leeres Python-HTML Projekt</option>
          <option value="python_logic">Python-HTML mit Python-Logik</option>
          <option value="event_logic">Python-HTML mit Event-Handler-Logik</option>
          <option value="kniffel_demo">🎲 Demo: Kniffel (Yahtzee)</option>
          <option value="blackjack_demo">🎰 Demo: Blackjack</option>
        </select>
      </div>
      <div class="modal-footer">
        <button id="free-template-cancel">Abbrechen</button>
        <button id="free-template-create" class="primary">Erstellen</button>
      </div>
    </div>
  </div>

<?php
require_once __DIR__ . '/../api/projects/templates.php';
$freeTemplateKeys = ['empty_python', 'empty_python_html', 'python_logic', 'event_logic', 'kniffel_demo', 'blackjack_demo'];
$freeTemplates = [];
foreach ($freeTemplateKeys as $key) {
  $freeTemplates[$key] = ProjectTemplates::getTemplate($key);
}
?>
  <script>
    window.FREE_PROJECT_TEMPLATES = <?= json_encode($freeTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    (function initTheme() {
      const html = document.documentElement;
      const themeBtn = document.getElementById('theme-toggle');
      const savedTheme = localStorage.getItem('theme') || 'light';
      if (savedTheme === 'dark') html.classList.add('dark-mode');
      themeBtn?.addEventListener('click', () => {
        html.classList.toggle('dark-mode');
        localStorage.setItem('theme', html.classList.contains('dark-mode') ? 'dark' : 'light');
      });
    })();

    document.querySelectorAll('.output-plot-tab').forEach((tab) => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.output-plot-tab').forEach((t) => t.classList.remove('active'));
        document.querySelectorAll('.output-plot-panel').forEach((p) => p.classList.remove('active'));
        tab.classList.add('active');
        const panel = document.getElementById(`${tab.dataset.tab}-container`);
        if (panel) panel.classList.add('active');
      });
    });

    (function initColumnSplitter() {
      const splitter = document.getElementById('column-splitter');
      const app = document.getElementById('editor-view');
      const editorArea = document.querySelector('.editor-area');
      const rightCol = document.querySelector('.right');
      if (!splitter || !app || !editorArea || !rightCol) return;

      let isDragging = false;
      let startX = 0;
      let startLeftWidth = 0;
      let startRightWidth = 0;

      splitter.addEventListener('pointerdown', (e) => {
        isDragging = true;
        startX = e.clientX;
        startLeftWidth = editorArea.getBoundingClientRect().width;
        startRightWidth = rightCol.getBoundingClientRect().width;
        splitter.classList.add('dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        splitter.setPointerCapture(e.pointerId);
      });

      document.addEventListener('pointermove', (e) => {
        if (!isDragging) return;
        const deltaX = e.clientX - startX;
        const totalWidth = startLeftWidth + startRightWidth;
        let newLeft = startLeftWidth + deltaX;
        let newRight = startRightWidth - deltaX;
        const minWidth = 280;
        if (newLeft < minWidth) { newLeft = minWidth; newRight = totalWidth - minWidth; }
        if (newRight < minWidth) { newRight = minWidth; newLeft = totalWidth - minWidth; }
        const sidebar = document.getElementById('project-list-panel');
        const sidebarWidth = sidebar ? Math.round(sidebar.getBoundingClientRect().width) : 440;
        app.style.gridTemplateColumns = `${sidebarWidth}px ${newLeft}px 5px ${newRight}px`;
      });

      const stopDragging = () => {
        isDragging = false;
        splitter.classList.remove('dragging');
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
      };
      document.addEventListener('pointerup', stopDragging);
      document.addEventListener('pointercancel', stopDragging);
    })();

    const FREE_STATE = {
      files: {},
      currentPath: null,
      template: null,
    };

    function extensionToLanguage(path) {
      const p = String(path || '').toLowerCase();
      if (p.endsWith('.py')) return 'python';
      if (p.endsWith('.html') || p.endsWith('.htm')) return 'html';
      if (p.endsWith('.css')) return 'css';
      if (p.endsWith('.js')) return 'javascript';
      if (p.endsWith('.json')) return 'json';
      if (p.endsWith('.md')) return 'markdown';
      return 'plaintext';
    }

    function cloneTemplateFiles(templateKey) {
      const tpl = window.FREE_PROJECT_TEMPLATES?.[templateKey] || window.FREE_PROJECT_TEMPLATES?.empty_python;
      const files = {};
      Object.entries(tpl.files || {}).forEach(([name, def]) => {
        files[name] = String(def.content || '');
      });
      return files;
    }

    function buildTree(paths) {
      const root = {};
      paths.forEach((path) => {
        const parts = path.split('/');
        let node = root;
        parts.forEach((part, idx) => {
          if (!node[part]) {
            node[part] = idx === parts.length - 1 ? null : {};
          }
          if (node[part] !== null) node = node[part];
        });
      });
      return root;
    }

    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderTreeNode(node, parentPath = '', depth = 0) {
      const entries = Object.keys(node).sort((a, b) => {
        const aIsFolder = node[a] !== null;
        const bIsFolder = node[b] !== null;
        if (aIsFolder !== bIsFolder) return aIsFolder ? -1 : 1;
        return a.localeCompare(b);
      });

      return entries.map((name) => {
        const isFolder = node[name] !== null;
        const fullPath = parentPath ? `${parentPath}/${name}` : name;
        if (isFolder) {
          return `<div class="free-tree-item free-folder" style="padding-left:${8 + depth * 14}px">
              <span>📁</span><span class="free-tree-name">${escHtml(name)}</span>
              <span class="free-tree-actions">
                <button data-action="new-file-in" data-folder="${escHtml(fullPath)}" title="Neue Datei">📄+</button>
                <button data-action="new-folder-in" data-folder="${escHtml(fullPath)}" title="Neuer Ordner">📁+</button>
                <button data-action="rename-folder" data-folder="${escHtml(fullPath)}" title="Umbenennen">✏️</button>
                <button data-action="delete-folder" data-folder="${escHtml(fullPath)}" title="Löschen">🗑️</button>
              </span>
            </div>${renderTreeNode(node[name], fullPath, depth + 1)}`;
        }
        const activeClass = FREE_STATE.currentPath === fullPath ? 'active' : '';
        return `<div class="free-tree-item ${activeClass}" data-path="${escHtml(fullPath)}" style="padding-left:${8 + depth * 14}px">
            <span>📄</span><span class="free-tree-name">${escHtml(name)}</span>
            <span class="free-tree-actions">
              <button data-action="rename-file" data-path="${escHtml(fullPath)}" title="Umbenennen">✏️</button>
              <button data-action="delete-file" data-path="${escHtml(fullPath)}" title="Löschen">🗑️</button>
            </span>
          </div>`;
      }).join('');
    }

    function renderFreeTree() {
      const treeEl = document.getElementById('project-file-tree');
      if (!treeEl) return;
      const paths = Object.keys(FREE_STATE.files);
      if (!paths.length) {
        treeEl.innerHTML = '<div style="padding:8px;color:var(--text-secondary)">Keine Dateien vorhanden.</div>';
        return;
      }
      treeEl.innerHTML = renderTreeNode(buildTree(paths));
    }

    function updateDetails() {
      const el = document.getElementById('free-project-details-content');
      if (!el) return;
      const count = Object.keys(FREE_STATE.files).length;
      el.innerHTML = `
        <div><strong>Template:</strong> ${FREE_STATE.template || '-'}</div>
        <div style="margin-top:6px;"><strong>Dateien:</strong> ${count}</div>
        <div style="margin-top:10px;color:var(--text-secondary)">Aktive Datei: ${FREE_STATE.currentPath || '-'}</div>
      `;
    }

    async function waitForEditor() {
          function updateFilenameLabel(path) {
            const label = document.getElementById('editor-filename-label');
            if (label) label.textContent = path ? path.split('/').pop() : '';
          }

      const start = Date.now();
      while (!window.editorInstance) {
        if (Date.now() - start > 12000) return null;
        await new Promise((r) => setTimeout(r, 80));
      }
      return window.editorInstance;
    }

    async function openFile(path) {
      const editor = await waitForEditor();
      if (!editor || !Object.prototype.hasOwnProperty.call(FREE_STATE.files, path)) return;

      if (FREE_STATE.currentPath && FREE_STATE.currentPath !== path) {
        FREE_STATE.files[FREE_STATE.currentPath] = editor.getValue();
      }

      FREE_STATE.currentPath = path;
      const model = editor.getModel();
      if (model) {
        monaco.editor.setModelLanguage(model, extensionToLanguage(path));
      }
      editor.setValue(FREE_STATE.files[path] || '');
      renderFreeTree();
      updateDetails();
      updateFilenameLabel(path);
    }

    async function createWorkspaceFromTemplate(templateKey) {
      FREE_STATE.files = cloneTemplateFiles(templateKey);
      FREE_STATE.template = templateKey;
      const firstPath = Object.keys(FREE_STATE.files).sort()[0] || null;
      FREE_STATE.currentPath = null;
      renderFreeTree();
      updateDetails();
      if (firstPath) await openFile(firstPath);
    }

    function freeNewFile(parentFolder) {
      const msg = parentFolder ? `Dateiname in "${parentFolder}":` : 'Dateiname (z.B. main.py oder src/helper.py):';
      const name = prompt(msg);
      if (!name || !name.trim()) return;
      const safeName = name.trim().replace(/^\/+/, '');
      const fullPath = parentFolder ? `${parentFolder}/${safeName}` : safeName;
      if (FREE_STATE.files[fullPath] !== undefined) { alert('Datei existiert bereits.'); return; }
      FREE_STATE.files[fullPath] = '';
      renderFreeTree();
      updateDetails();
      openFile(fullPath);
    }

    function freeNewFolder(parentFolder) {
      const name = prompt(parentFolder ? `Ordnername in "${parentFolder}":` : 'Ordnername:');
      if (!name || !name.trim()) return;
      const safeName = name.trim().replace(/[\/\\]/g, '');
      if (!safeName) return;
      const folderPath = parentFolder ? `${parentFolder}/${safeName}` : safeName;
      const placeholder = `${folderPath}/.gitkeep`;
      if (FREE_STATE.files[placeholder] !== undefined) { alert('Ordner existiert bereits.'); return; }
      FREE_STATE.files[placeholder] = '';
      renderFreeTree();
      updateDetails();
    }

    async function freeRenameFile(oldPath) {
      const parts = oldPath.split('/');
      const oldName = parts.pop();
      const parent = parts.join('/');
      const newName = prompt('Neuer Dateiname:', oldName);
      if (!newName || !newName.trim() || newName.trim() === oldName) return;
      const newPath = parent ? `${parent}/${newName.trim()}` : newName.trim();
      if (FREE_STATE.files[newPath] !== undefined) { alert('Datei existiert bereits.'); return; }
      const editor = window.editorInstance;
      if (editor && FREE_STATE.currentPath === oldPath) FREE_STATE.files[oldPath] = editor.getValue();
      FREE_STATE.files[newPath] = FREE_STATE.files[oldPath];
      delete FREE_STATE.files[oldPath];
      if (FREE_STATE.currentPath === oldPath) {
        FREE_STATE.currentPath = null;
        await openFile(newPath);
      } else {
        renderFreeTree();
        updateDetails();
      }
    }

    async function freeDeleteFile(path) {
      if (!confirm(`Datei "${path}" löschen?`)) return;
      const editor = window.editorInstance;
      if (editor && FREE_STATE.currentPath === path) FREE_STATE.files[path] = editor.getValue();
      delete FREE_STATE.files[path];
      if (FREE_STATE.currentPath === path) {
        FREE_STATE.currentPath = null;
        if (editor) editor.setValue('');
        updateFilenameLabel('');
      }
      renderFreeTree();
      updateDetails();
    }

    async function freeRenameFolder(folderPath) {
      const parts = folderPath.split('/');
      const oldName = parts.pop();
      const parent = parts.join('/');
      const newName = prompt('Neuer Ordnername:', oldName);
      if (!newName || !newName.trim() || newName.trim() === oldName) return;
      const safeName = newName.trim().replace(/[\/\\]/g, '');
      if (!safeName) return;
      const newFolderPath = parent ? `${parent}/${safeName}` : safeName;
      const prefix = folderPath + '/';
      const newPrefix = newFolderPath + '/';
      const renames = Object.keys(FREE_STATE.files).filter(p => p.startsWith(prefix)).map(p => [p, newPrefix + p.slice(prefix.length)]);
      if (!renames.length) return;
      const editor = window.editorInstance;
      if (editor && FREE_STATE.currentPath) FREE_STATE.files[FREE_STATE.currentPath] = editor.getValue();
      let newCurrentPath = FREE_STATE.currentPath;
      renames.forEach(([oldP, newP]) => {
        FREE_STATE.files[newP] = FREE_STATE.files[oldP];
        delete FREE_STATE.files[oldP];
        if (FREE_STATE.currentPath === oldP) newCurrentPath = newP;
      });
      FREE_STATE.currentPath = null;
      if (newCurrentPath && FREE_STATE.files[newCurrentPath] !== undefined) {
        await openFile(newCurrentPath);
      } else {
        renderFreeTree();
        updateDetails();
      }
    }

    async function freeFolderDelete(folderPath) {
      const prefix = folderPath + '/';
      const filesToDelete = Object.keys(FREE_STATE.files).filter(p => p.startsWith(prefix));
      if (!confirm(`Ordner "${folderPath}" mit ${filesToDelete.length} Datei(en) löschen?`)) return;
      const editor = window.editorInstance;
      if (editor && FREE_STATE.currentPath && FREE_STATE.currentPath.startsWith(prefix)) {
        FREE_STATE.files[FREE_STATE.currentPath] = editor.getValue();
      }
      filesToDelete.forEach(p => delete FREE_STATE.files[p]);
      if (FREE_STATE.currentPath && FREE_STATE.currentPath.startsWith(prefix)) {
        FREE_STATE.currentPath = null;
        if (editor) editor.setValue('');
        updateFilenameLabel('');
      }
      renderFreeTree();
      updateDetails();
    }

    document.getElementById('file-tree-wrapper')?.addEventListener('click', async (e) => {
      const actionBtn = e.target.closest('[data-action]');
      if (actionBtn) {
        const action = actionBtn.dataset.action;
        if (action === 'new-file-root') freeNewFile('');
        else if (action === 'new-folder-root') freeNewFolder('');
        else if (action === 'new-file-in') freeNewFile(actionBtn.dataset.folder);
        else if (action === 'new-folder-in') freeNewFolder(actionBtn.dataset.folder);
        else if (action === 'rename-file') await freeRenameFile(actionBtn.dataset.path);
        else if (action === 'delete-file') await freeDeleteFile(actionBtn.dataset.path);
        else if (action === 'rename-folder') await freeRenameFolder(actionBtn.dataset.folder);
        else if (action === 'delete-folder') await freeFolderDelete(actionBtn.dataset.folder);
        return;
      }
      const row = e.target.closest('[data-path]');
      if (!row) return;
      await openFile(row.dataset.path);
    });

    document.getElementById('free-new-empty-btn')?.addEventListener('click', async () => {
      await createWorkspaceFromTemplate('empty_python');
    });

    document.getElementById('free-new-ui-btn')?.addEventListener('click', async () => {
      await createWorkspaceFromTemplate('empty_python_html');
    });

    document.getElementById('free-new-template-btn')?.addEventListener('click', () => {
      document.getElementById('free-template-modal')?.classList.add('open');
    });

    document.getElementById('free-template-cancel')?.addEventListener('click', () => {
      document.getElementById('free-template-modal')?.classList.remove('open');
    });

    document.getElementById('free-template-create')?.addEventListener('click', async () => {
      const select = document.getElementById('free-template-select');
      const templateKey = select?.value || 'empty_python';
      document.getElementById('free-template-modal')?.classList.remove('open');
      await createWorkspaceFromTemplate(templateKey);
    });

    document.addEventListener('DOMContentLoaded', async () => {
      await createWorkspaceFromTemplate('empty_python');
      const editor = await waitForEditor();
      if (editor) {
        editor.onDidChangeModelContent(() => {
          if (!FREE_STATE.currentPath) return;
          FREE_STATE.files[FREE_STATE.currentPath] = editor.getValue();
        });
      }
    });

    document.getElementById('download-file-btn')?.addEventListener('click', () => {
      if (!FREE_STATE.currentPath) { alert('Keine Datei geöffnet.'); return; }
      const content = FREE_STATE.files[FREE_STATE.currentPath] || '';
      const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = FREE_STATE.currentPath.replace(/^.*[\/\\]/, '');
      a.click();
      URL.revokeObjectURL(a.href);
    });

    document.getElementById('download-zip-btn')?.addEventListener('click', async () => {
      const files = FREE_STATE.files;
      if (!Object.keys(files).length) { alert('Kein Workspace vorhanden.'); return; }
      if (typeof JSZip === 'undefined') { alert('JSZip nicht geladen. Bitte Internetverbindung prüfen.'); return; }
      const zip = new JSZip();
      for (const [path, content] of Object.entries(files)) {
        zip.file(path, content);
      }
      const blob = await zip.generateAsync({ type: 'blob' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'workspace.zip';
      a.click();
      URL.revokeObjectURL(a.href);
    });
  </script>
</body>
</html>
