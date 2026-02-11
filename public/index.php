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
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Python IDE - HS Pforzheim</title>
  <link rel="stylesheet" href="css/hspf-theme.css">
  <style>
    body {
      background: linear-gradient(135deg, 
        rgba(255, 190, 49, 0.02) 0%, 
        rgba(125, 115, 105, 0.03) 100%
      ),
      var(--hspf-bg);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .landing-content {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--hspf-spacing-2xl) var(--hspf-spacing-lg);
    }

    .landing-shell {
      width: 100%;
      max-width: 1100px;
    }

    .landing-hero {
      text-align: center;
      margin-bottom: var(--hspf-spacing-2xl);
    }

    .landing-hero h1 {
      font-size: 42px;
      font-weight: 300;
      color: var(--hspf-primary);
      margin: 0 0 var(--hspf-spacing-md) 0;
      letter-spacing: -0.02em;
    }

    .landing-tagline {
      font-size: 18px;
      color: var(--hspf-text-secondary);
      margin: 0 auto;
      max-width: 600px;
      line-height: 1.6;
    }

    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: var(--hspf-spacing-lg);
      margin-bottom: var(--hspf-spacing-xl);
    }

    .landing-card {
      background: var(--hspf-surface);
      border: 2px solid var(--hspf-border);
      border-radius: var(--hspf-radius-lg);
      padding: var(--hspf-spacing-xl);
      box-shadow: var(--hspf-shadow-lg);
      transition: var(--hspf-transition);
      display: flex;
      flex-direction: column;
      gap: var(--hspf-spacing-md);
      min-height: 240px;
    }

    .landing-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--hspf-shadow-xl);
      border-color: var(--hspf-accent);
    }

    .landing-card h2 {
      font-size: 24px;
      font-weight: 300;
      color: var(--hspf-primary);
      margin: 0;
    }

    .landing-card p {
      font-size: 15px;
      color: var(--hspf-text-secondary);
      line-height: 1.6;
      margin: 0;
      flex: 1;
    }

    .landing-cta {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 14px 24px;
      border-radius: var(--hspf-radius);
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      transition: var(--hspf-transition);
      border: 2px solid transparent;
      cursor: pointer;
    }

    .landing-cta.primary {
      background: var(--hspf-accent);
      color: var(--hspf-primary);
      border-color: var(--hspf-accent);
    }

    .landing-cta.primary:hover {
      background: var(--hspf-accent-hover);
      border-color: var(--hspf-accent-hover);
      transform: translateY(-2px);
    }

    .landing-cta.secondary {
      background: transparent;
      color: var(--hspf-primary);
      border-color: var(--hspf-primary);
    }

    .landing-cta.secondary:hover {
      background: var(--hspf-primary);
      color: white;
      transform: translateY(-2px);
    }

    .landing-meta {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: var(--hspf-spacing-md);
      margin-top: var(--hspf-spacing-xl);
    }

    .landing-chip {
      display: inline-flex;
      align-items: center;
      gap: var(--hspf-spacing-sm);
      padding: var(--hspf-spacing-sm) var(--hspf-spacing-md);
      border-radius: var(--hspf-radius-full);
      background: var(--hspf-surface);
      border: 1px solid var(--hspf-border);
      font-family: var(--hspf-font-mono);
      font-size: 13px;
      color: var(--hspf-text-secondary);
      box-shadow: var(--hspf-shadow-sm);
    }

    @media (max-width: 768px) {
      .landing-hero h1 { font-size: 32px; }
      .landing-tagline { font-size: 16px; }
      .card-grid { grid-template-columns: 1fr; }
      .landing-content { padding: var(--hspf-spacing-xl) var(--hspf-spacing-md); }
    }
  </style>
</head>
<body>
  <?php 
  $pageTitle = '';
  include(__DIR__ . '/../components/header.php'); 
  ?>

  <main class="landing-content">
    <div class="landing-shell">
      <div class="landing-hero">
        <h1>Python IDE</h1>
        <p class="landing-tagline">Starte sofort im Free Editor oder melde dich an, um Projekte zu speichern und zu teilen.</p>
      </div>

      <section class="card-grid">
        <article class="landing-card">
          <h2>Free Editor</h2>
          <p>Ohne Anmeldung direkt loslegen. Ideal zum schnellen Testen, Lernen und Ausprobieren von Code.</p>
          <a class="landing-cta primary" href="free.php">Free Editor starten</a>
        </article>

        <article class="landing-card">
          <h2>Login / Registrierung</h2>
          <p>Speichere Projekte, teile Links, arbeite an Aufgaben und bleib uebersichtlich organisiert.</p>
          <a class="landing-cta secondary" href="login.php">Anmelden oder Registrieren</a>
        </article>
      </section>

      <div class="landing-meta">
        <span class="landing-chip">Pyodide · Python im Browser</span>
        <span class="landing-chip">Monaco Editor</span>
        <span class="landing-chip">Autosave & Sharing</span>
      </div>
    </div>
  </main>
</body>
</html>
