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
  <title>Python IDE - Start</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:#f6f1ea;
      --panel:#fffaf4;
      --ink:#1c1b1a;
      --muted:#6c6762;
      --accent:#0f766e;
      --accent-2:#f59e0b;
      --border:#e6ddd4;
      --shadow:0 18px 60px rgba(15, 23, 42, 0.12);
    }

    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family:"Space Grotesk", system-ui, -apple-system, "Segoe UI", Roboto, Arial;
      color:var(--ink);
      background:radial-gradient(1200px 600px at 20% -10%, #e5f6f2, transparent),
                 radial-gradient(900px 500px at 110% 10%, #fef3c7, transparent),
                 var(--bg);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:32px 18px 48px;
    }

    .shell{
      width:100%;
      max-width:980px;
      position:relative;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:12px;
      margin-bottom:18px;
    }
    .brand-mark{
      width:44px;
      height:44px;
      border-radius:14px;
      background:conic-gradient(from 210deg, #0f766e, #f59e0b, #22c55e, #0f766e);
      box-shadow:0 12px 30px rgba(15, 118, 110, 0.25);
    }
    .brand h1{
      margin:0;
      font-size:28px;
      letter-spacing:-0.02em;
    }
    .tagline{
      margin:0 0 26px 0;
      color:var(--muted);
      font-size:16px;
      max-width:520px;
    }

    .card-grid{
      display:grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap:18px;
    }

    .card{
      background:var(--panel);
      border:1px solid var(--border);
      border-radius:18px;
      padding:22px 22px 20px;
      box-shadow:var(--shadow);
      position:relative;
      overflow:hidden;
      min-height:220px;
      display:flex;
      flex-direction:column;
      gap:12px;
    }
    .card::after{
      content:"";
      position:absolute;
      inset:auto -40px -40px auto;
      width:140px;
      height:140px;
      background:radial-gradient(circle, rgba(15, 118, 110, 0.18), transparent 65%);
      pointer-events:none;
    }
    .card h2{
      margin:0;
      font-size:20px;
    }
    .card p{
      margin:0;
      color:var(--muted);
      line-height:1.5;
      font-size:14px;
    }

    .cta{
      margin-top:auto;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      padding:12px 16px;
      border-radius:12px;
      border:1px solid transparent;
      background:var(--accent);
      color:#fff;
      text-decoration:none;
      font-weight:600;
      transition:transform 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .cta.secondary{
      background:transparent;
      color:var(--accent);
      border-color:var(--accent);
    }
    .cta:hover{
      transform:translateY(-2px);
      box-shadow:0 12px 26px rgba(15, 118, 110, 0.25);
    }

    .meta{
      margin-top:24px;
      display:flex;
      flex-wrap:wrap;
      gap:12px 20px;
      font-size:13px;
      color:var(--muted);
    }
    .chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:6px 10px;
      border-radius:999px;
      background:#fff;
      border:1px solid var(--border);
      font-family:"IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size:12px;
    }

    @media (max-width: 680px) {
      body{ padding:24px 14px 38px; }
      .brand h1{ font-size:24px; }
      .card{ min-height:200px; }
    }
  </style>
</head>
<body>
  <main class="shell">
    <div class="brand">
      <div class="brand-mark" aria-hidden="true"></div>
      <h1>Python IDE</h1>
    </div>
    <p class="tagline">Starte sofort im Free Editor oder melde dich an, um Projekte zu speichern und zu teilen.</p>

    <section class="card-grid">
      <article class="card">
        <h2>Free Editor</h2>
        <p>Ohne Anmeldung direkt loslegen. Ideal zum schnellen Testen, Lernen und Ausprobieren von Code.</p>
        <a class="cta" href="free.php">Free Editor starten</a>
      </article>

      <article class="card">
        <h2>Login / Registrierung</h2>
        <p>Speichere Projekte, teile Links, arbeite an Aufgaben und bleib uebersichtlich organisiert.</p>
        <a class="cta secondary" href="login.php">Anmelden oder Registrieren</a>
      </article>
    </section>

    <div class="meta">
      <span class="chip">Pyodide · Python im Browser</span>
      <span class="chip">Monaco Editor</span>
      <span class="chip">Autosave & Sharing</span>
    </div>
  </main>
</body>
</html>
