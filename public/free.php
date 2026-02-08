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
    #settings-toggle{ width:34px; height:34px; padding:0; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px; }
    #theme-toggle{ width:40px; height:24px; border-radius:999px; border:1px solid var(--border); background:var(--panel); cursor:pointer; display:flex; align-items:center; padding:2px; transition:background 0.3s; }
    #theme-toggle::after{ content:'🌙'; font-size:14px; display:block; width:20px; height:20px; line-height:20px; transition:transform 0.3s; }
    html.dark-mode #theme-toggle::after{ content:'☀️'; }
    .toolcheck{ display:flex; gap:6px; align-items:center; padding:6px 10px; border:1px solid var(--border); border-radius:999px; background:var(--panel); color:var(--text-primary); }
    .toolcheck input{ transform: translateY(0.5px); }

    .settings-panel{
      position:fixed;
      top:58px;
      right:10px;
      z-index:120;
      background:var(--panel);
      color:var(--text-primary);
      border:1px solid var(--border);
      border-radius:10px;
      padding:10px;
      min-width:190px;
      display:none;
      flex-direction:column;
      gap:8px;
      box-shadow:0 12px 30px rgba(0,0,0,0.12);
    }
    .settings-panel.open{ display:flex; }
    .settings-title{
      font-size:12px;
      font-weight:700;
      letter-spacing:0.04em;
      text-transform:uppercase;
      color:var(--text-secondary);
    }

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
    
    html.dark-mode #lint-container {
      background: #252526;
      color: #cccccc;
    }
    #lint-container .lint-checking{ color:var(--text-secondary); font-weight:500; }
    #lint-container .lint-ok{ color:var(--text-primary); font-weight:600; }
    #lint-container .lint-checkmark{ color:#22c55e; font-weight:700; }
    #lint-container .lint-fix-label{ color:var(--text-primary); font-weight:600; }
    #lint-container .lint-fix-link{ cursor:pointer; text-decoration:underline; color:#2563eb; }
    html.dark-mode #lint-container .lint-fix-link{ color:#60a5fa; }
    #help-container{
      padding:6px 8px;
      overflow:auto;
      background:var(--help-bg);
      color:var(--help-text);
      font-size:14px;
      line-height:1.6;
      min-width:0; min-height:0;
    }
    #help-container h1, #help-container h2, #help-container h3{
      margin:2px 0 6px 0;
      padding:0;
      font-size:1em;
    }
    #help-container p{
      margin:4px 0;
      padding:0;
    }
    #help-container .help-muted{ color:var(--text-secondary); margin:0; padding:0; }
    #help-container pre{
      background:var(--code-bg);
      color:var(--code-color);
      padding:8px;
      border-radius:4px;
      overflow-x:auto;
      margin:4px 0;
      border-left:2px solid var(--border);
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:0.9em;
      line-height:1.4;
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

    /* Autocomplete: light background, semi-transparent to see help behind */
    .monaco-editor .suggest-widget{
      z-index:100 !important;
      opacity:0.9 !important;
      background:rgba(245, 245, 250, 0.95) !important;
      border:1px solid rgba(180, 180, 190, 0.9) !important;
      color:#333 !important;
    }
    .editor-widget.suggest-widget{
      z-index:100 !important;
      opacity:0.9 !important;
      background:rgba(245, 245, 250, 0.95) !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row{
      background:rgba(245, 245, 250, 0.95) !important;
      color:#333 !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row:hover{
      background:rgba(230, 235, 245, 0.95) !important;
    }
    .monaco-editor .suggest-widget .monaco-list-row.selected{
      background:rgba(220, 230, 245, 0.95) !important;
    }
    .monaco-editor .suggest-widget-details{
      background:rgba(245, 245, 250, 0.95) !important;
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

    <div style="flex:1"></div>
    <a href="login.php" style="padding:8px 16px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">Anmelden / Registrieren</a>
    <button id="settings-toggle" title="Module" aria-label="Module settings">⚙</button>
    <button id="theme-toggle" title="Light/Dark Mode" aria-label="Toggle theme"></button>
  </div>

  <div id="settings-panel" class="settings-panel" aria-hidden="true">
    <div class="settings-title">Module</div>
    <label class="toolcheck" title="NumPy laden">
      <input id="pkg-numpy" type="checkbox" checked>
      <span>NumPy</span>
    </label>
    <label class="toolcheck" title="Matplotlib laden">
      <input id="pkg-matplotlib" type="checkbox" checked>
      <span>Matplotlib</span>
    </label>
    <label class="toolcheck" title="Pandas laden">
      <input id="pkg-pandas" type="checkbox">
      <span>Pandas</span>
    </label>
    <label class="toolcheck" title="Panel nicht verfuegbar in Pyodide">
      <input id="pkg-panel" type="checkbox" disabled>
      <span>Panel (nicht verfuegbar)</span>
    </label>
    <label class="toolcheck" title="Seaborn nicht verfuegbar in Pyodide">
      <input id="pkg-seaborn" type="checkbox" disabled>
      <span>Seaborn (nicht verfuegbar)</span>
    </label>
  </div>

  <div class="app">
    <div class="left">
      <div id="editor-container"></div>

      <div class="left-bottom">
        <div id="lint-container"></div>
        <div id="help-container">
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
