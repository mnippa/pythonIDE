export function detectiveSupported(task) {
  return task?.task_type === 'code' && !!task.test_cases
    && ![true, 1, '1'].includes(task.folderstructure);
}

export function detectiveValueDifference(actual, expected, path) {
  if (typeof actual === 'string' && actual.trim() === '' && typeof expected === 'string' && expected.trim() !== '') {
    return `Die Prüfvariable „${path}“ ist leer.`;
  }
  const owns = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
  const format = value => {
    const text = JSON.stringify(value) ?? 'nicht vorhanden';
    return text.length > 100 ? `${text.slice(0, 100)}…` : text;
  };
  if (typeof actual === 'string' && typeof expected === 'string' && actual && expected.includes(actual) && expected !== actual) {
    const index = expected.indexOf(actual);
    const prefix = expected.slice(0, index);
    const suffix = expected.slice(index + actual.length);
    const missingParts = [
      prefix ? `Der erwartete Textanfang ${format(prefix)} fehlt` : '',
      suffix ? `der erwartete Text am Ende ${format(suffix)} fehlt` : ''
    ].filter(Boolean).join('; ');
    if (missingParts) return `${path}: erhalten ${format(actual)}, erwartet ${format(expected)}. ${missingParts}.`;
  }
  const kind = value => value === null ? 'None' : Array.isArray(value) ? 'Liste'
    : typeof value === 'object' ? 'Dictionary' : typeof value === 'string' ? 'Text'
      : typeof value === 'boolean' ? 'Wahrheitswert' : typeof value === 'number' ? 'Zahl' : 'nicht vorhanden';
  if (kind(actual) !== kind(expected)) return `${path}: ${kind(actual)} statt ${kind(expected)}.`;
  if (expected && typeof expected === 'object') {
    if (Array.isArray(expected) && actual.length !== expected.length) {
      return `${path}: Die Liste enthält ${actual.length} statt ${expected.length} Elemente. Prüfe, ob jedes erwartete Element zur Liste hinzugefügt wird.`;
    }
    for (const key of Object.keys(expected)) {
      if (!owns(actual, key)) {
        const extra = Object.keys(actual).filter(k => !owns(expected, k));
        return `${path}: Die Prüfung erwartet den Schlüssel ${format(key)}.${extra.length ? ` Zusätzlich vorhanden: ${extra.slice(0, 3).map(format).join(', ')}.` : ''}`;
      }
      if (JSON.stringify(actual[key]) !== JSON.stringify(expected[key])) {
        return detectiveValueDifference(actual[key], expected[key], `${path}[${Array.isArray(expected) ? key : format(key)}]`);
      }
    }
    const extra = Object.keys(actual).find(key => !owns(expected, key));
    if (extra !== undefined) return `${path}: Zusätzlich vorhanden ist der Schlüssel ${format(extra)}.`;
  }
  return `${path}: erhalten ${format(actual)}, erwartet ${format(expected)}.`;
}

export function detectiveRuntimeError(error) {
  const message = String(error || '').replace(/^Fehler:\s*/, '');
  const undefinedName = message.match(/name ['"]([^'"]+)['"] is not defined/);
  if (undefinedName) {
    return `Python kennt die Variable „${undefinedName[1]}“ an dieser Stelle nicht. Prüfe die Schreibweise und ob du ihr vor der Verwendung einen Wert zuweist.`;
  }
  if (/unsupported operand type.*['"]str['"]|can only concatenate str/i.test(message)) {
    return 'Hier werden Text und andere Datentypen auf eine nicht unterstützte Weise verknüpft. Prüfe die beteiligten Werte und wandle sie bei Bedarf in passende Datentypen um.';
  }
  if (/can't multiply sequence by non-int/i.test(message)) {
    return 'Eine Folge, zum Beispiel eine Liste oder ein Text, kann nur mit einer ganzen Zahl vervielfacht werden. Prüfe den Multiplikator.';
  }
  if (/could not convert string to (?:float|int):\s*['"]{2}|invalid literal for int\(\).*['"]{2}/i.test(message)) {
    return 'Ein leerer Text kann nicht in eine Zahl umgewandelt werden. Prüfe den Wert vor der Umwandlung mit float() oder int().';
  }
  if (/could not convert string to (?:float|int):|invalid literal for int\(\)/i.test(message)) {
    return 'Der Text kann nicht in eine Zahl umgewandelt werden. Prüfe den Wert vor der Umwandlung mit float() oder int().';
  }
  if (/No more INPUT|No more mock_inputs|EOFError/i.test(message)) {
    return 'Das Programm fordert mehr Eingaben an, als der Test bereitstellt. Prüfe, ob die Schleife beim vorgesehenen Abbruchsignal endet oder die richtige Anzahl Werte verarbeitet.';
  }
  return message.slice(0, 180);
}

export function summarizeDetective(results, context = {}) {
  const messages = [];
  const { names = [], functions = [], absent = [], functionTargets = [], findings = [], outputHint = null, specs = [], mapped = false, ruleValues = [] } = context;
  const missingAutomaticFunction = result => result.type === 'function' && !functions.length
    && /Function '(?:None|null)' not found/.test(result.error || '');
  if (results.some(missingAutomaticFunction)) messages.push('Für diese Aufgabe musst du eine Funktion schreiben; bisher ist keine eigene Funktion definiert.');
  for (const target of absent) {
    const fn = functionTargets.includes(target);
    const pool = fn ? functions : names;
    const similar = pool.filter(name => name !== target && detectiveNameDistance(name, target) <= (target.length >= 7 ? 2 : 1));
    if (fn && !functions.length) {
      messages.push(`Die Prüfung erwartet die Funktion „${target}“.${names.includes(target) ? ` Der Name „${target}“ ist vorhanden, aber nicht als Funktion.` : ''} Du musst eine Funktion schreiben; bisher ist keine eigene Funktion definiert.`);
    } else {
      messages.push(`Die Prüfung erwartet ${fn ? 'eine Funktion' : 'eine Variable'} namens „${target}“.${similar.length === 1
        ? ` Vorhanden ist „${similar[0]}“ – möglicherweise ein Tippfehler.`
        : fn ? ` Vorhandene Funktionen: ${functions.slice(0, 4).map(n => `„${n}“`).join(', ')}. Prüfe die verlangte Benennung.`
          : ' Unter diesem Namen liegt kein Ergebnis vor.'}`);
    }
  }
  messages.push(...findings);
  const emptyMismatch = detail => !detail.matches && typeof detail.actual === 'string'
    && detail.actual.trim() === '' && typeof detail.expected === 'string' && detail.expected.trim() !== '';
  const emptyNames = [...new Set(results.filter(r => !r.passed && !r.error
    && (r.type === 'variable' || r.type === 'intelligent' && r.mode === 'vars'))
    .flatMap(r => (r.matchDetails || []).filter(emptyMismatch).map(detail => detail.varName || detail.name)))];
  for (const name of emptyNames) {
    if (!findings.some(message => message.includes(`„${name}“`) && /leer/.test(message))) {
      messages.push(`Die Prüfvariable „${name}“ ist leer.`);
    }
  }
  const rules = results.filter(r => r.type === 'code_check' && !r.passed);
  const explained = rules.filter(r => typeof r.feedback === 'string' && r.feedback.trim());
  for (const rule of explained) messages.push(`Nicht erfüllte ${rule.operator === 'NOT' ? 'Einschränkung' : 'Vorgabe'}: ${rule.feedback.trim()}`);
  const missing = rules.filter(r => r.operator !== 'NOT' && !explained.includes(r)).length;
  const forbidden = rules.filter(r => r.operator === 'NOT' && !explained.includes(r)).length;
  if (missing) messages.push('Die Schlüsselwortprüfung legt nahe, dass geforderte Codeelemente fehlen. Gleiche deinen Code mit den Vorgaben in der Beschreibung ab.');
  if (forbidden) messages.push(`${forbidden} Verbotsregel(n) sind verletzt: Dein Code enthält nicht erlaubte Elemente. Prüfe die Einschränkungen in der Beschreibung.`);
  for (const value of ruleValues.filter(v => !v.matches).slice(0, 2)) {
    messages.push(typeof value.actual === 'string' && value.actual.trim() === '' && typeof value.expected === 'string' && value.expected.trim() !== ''
      ? `Die Prüfvariable „${value.name}“ ist leer.`
      : `Wertabweichung bei „${value.name}“ im Vergleich mit der Musterlösung für diese Daten: ${detectiveValueDifference(value.actual, value.expected, value.name)}`);
  }
  const functional = results.filter(r => r.type !== 'code_check');
  const failed = functional.filter(r => !r.passed && !missingAutomaticFunction(r) && !absent.some(target =>
    /(?:Variable|Function|Funktion).*not found/.test(r.error || '') && String(r.error).includes(target)
    || functionTargets.includes(target) && r.functionName === target && /not callable/.test(r.error || '')));
  const example = failed.find(r => r.type !== 'output' && (r.error || r.type === 'function' || r.mode === 'function'
    || !(r.matchDetails || []).length || r.matchDetails.some(detail => !detail.matches && !emptyMismatch(detail))));
  if (example) {
    if (mapped) messages.push('Nach probeweiser Namenszuordnung zeigt sich zusätzlich:');
    const mismatch = example.matchDetails?.find(detail => !detail.matches && (example.mode === 'function' || !emptyMismatch(detail)));
    if (example.error) {
      messages.push(`Bei der Ausführung für die Prüfung tritt ein Fehler auf: ${detectiveRuntimeError(example.error)}`);
    } else if (example.type === 'function') {
      messages.push(`${example.functionName}(${(Array.isArray(example.args) ? example.args : [example.args]).filter(v => v !== undefined).map(v => JSON.stringify(v)).join(', ')}): erhalten ${String(example.output).slice(0, 100)}, erwartet ${String(example.expected).slice(0, 100)}.`);
    } else if (example.mode === 'function' && mismatch && emptyMismatch(mismatch)) {
      messages.push(`Der Rückgabewert von „${example.functionName}“ ist leer.`);
    } else if (mismatch) {
      if (example.values) messages.push(`Für die Prüfeingaben ${JSON.stringify(example.values).slice(0, 240)}:`);
      else if (example.initVars && Object.keys(example.initVars).length) messages.push(`Für die Prüfeingaben ${typeof example.initVars === 'string' ? example.initVars : JSON.stringify(example.initVars)}:`);
      const difference = detectiveValueDifference(mismatch.actual, mismatch.expected, mismatch.varName || mismatch.name);
      messages.push(difference);
      if (typeof mismatch.actual === 'number' && typeof mismatch.expected === 'number') {
        messages.push('Das Ergebnis weicht von der Musterlösung ab. Prüfe die Schritte, mit denen du diesen Wert berechnest, und vergleiche sie mit den Anforderungen der Aufgabe.');
      }
    } else {
      messages.push('Das Ergebnis weicht bei einer geprüften Eingabe vom erwarteten Ergebnis ab.');
    }
  }
  if (failed.some(r => r.type === 'output')) {
    if (outputHint) messages.push(outputHint);
    else if (!findings.some(message => message.startsWith('Die Ausgabe ist leer.'))) {
      const outputError = failed.find(r => r.type === 'output' && r.error);
      messages.push(outputError ? `Bei der Ausgabeprüfung tritt ein Fehler auf: ${String(outputError.error).slice(0, 180)}`
        : 'Die Ausgabe weicht von der Vorgabe ab. Prüfe Werte, Beschriftungen, Reihenfolge und Zeilenumbrüche.');
    }
  }
  const valueTests = functional.filter(r => r.type !== 'output');
  if (!ruleValues.some(v => !v.matches) && valueTests.length && valueTests.every(r => r.passed) && (rules.length || outputHint || failed.length || mapped)) {
    messages.unshift(mapped
      ? 'Nach der probeweisen Namenszuordnung stimmen die geprüften Ergebniswerte. Die Benennung im ursprünglichen Code entspricht noch nicht der Prüfvorgabe.'
      : 'Die geprüften Ergebniswerte stimmen; es gibt jedoch Abweichungen bei den Vorgaben zur Prüfung oder Ausgabe.');
  } else if (!ruleValues.length && !valueTests.length && functional.some(r => r.type === 'output' && r.passed) && rules.length) {
    messages.push('Die Ausgabe erfüllt den vorhandenen Ausgabetest. Ob sie inhaltlich richtig ist, lässt sich daraus allein nicht sicher ableiten.');
  }
  if (!messages.length) messages.push(specs.length || results.length
    ? 'Keine konkrete Abweichung erkennbar. Das ist keine vollständige Bewertung der fachlichen Richtigkeit.'
    : 'Keine auswertbaren Prüfergebnisse vorhanden.');
  return { messages };
}

// Runs only inside a disposable worker. Existing check functions are supplied unchanged.
export async function detectiveWorker(event) {
  const { task, code, indexURL } = event.data;
  try {
    importScripts(`${indexURL}pyodide.js`);
    const py = await loadPyodide({ indexURL, stdout: () => {}, stderr: () => {} });
    const specs = migrateLegacyTestCases(typeof task.test_cases === 'string'
      ? (() => {
        const parsed = JSON.parse(task.test_cases);
        return !Array.isArray(parsed) && parsed.mode ? [{ type: 'intelligent', ...parsed }] : parsed;
      })() : task.test_cases);
    if (!Array.isArray(specs) || !specs.length) throw new Error('Keine gültigen Prüfdaten vorhanden.');
    const groups = {};
    for (const spec of specs) {
      if (!spec || typeof spec !== 'object') throw new Error('Ungültige Prüfkonfiguration.');
      if (spec.type === 'code_check' && !['AND', 'OR', 'NOT'].includes(spec.operator || 'AND')) {
        throw new Error('Ungültige Verknüpfung der Codevorgaben.');
      }
      if (spec.type === 'output' && spec.expected_type === 'regex' && typeof spec.expected !== 'string') {
        throw new Error('Der Ausgabetest enthält keinen auswertbaren erwarteten regulären Ausdruck. Bitte die Aufgabenverwaltung informieren.');
      }
      (groups[spec.type || 'output'] ||= []).push(spec);
    }
    const supported = ['output', 'variable', 'function', 'intelligent', 'code_check'];
    if (Object.keys(groups).some(type => !supported.includes(type))) throw new Error('Diese Prüfart wird vom Detektiv noch nicht unterstützt.');
    const syntax = JSON.parse(await py.runPythonAsync(`
import ast, json
try:
    ast.parse(${JSON.stringify(code)})
except SyntaxError as error:
    _detective_syntax = {"kind": type(error).__name__, "line": error.lineno, "message": error.msg}
else:
    _detective_syntax = None
json.dumps(_detective_syntax)
`));
    if (syntax) {
      const location = Number.isInteger(syntax.line) && syntax.line > 0 ? ` in Zeile ${syntax.line}` : '';
      let hint;
      if (syntax.kind === 'TabError') {
        hint = 'Die Einrückung mischt Tabulatoren und Leerzeichen. Verwende einheitliche Einrückungen.';
      } else if (syntax.kind === 'IndentationError' && /expected an indented block/i.test(syntax.message)) {
        hint = 'Nach einer Anweisung wie def, if oder for fehlt ein eingerückter ausführbarer Block. Ein Kommentar allein genügt nicht.';
      } else if (syntax.kind === 'IndentationError') {
        hint = 'Die Einrückung passt nicht zum umgebenden Code. Prüfe die Leerzeichen und Tabulatoren.';
      } else {
        hint = `Prüfe die Schreibweise sowie Klammern, Anführungszeichen und Doppelpunkte. Python meldet: ${syntax.message}`;
      }
      self.postMessage({ ok: false, error: `Python meldet einen ${syntax.kind === 'SyntaxError' ? 'Syntaxfehler' : 'Einrückungsfehler'}${location}. ${hint}` });
      return;
    }
    await py.loadPackagesFromImports([
      code, task.solution_code || '', task.randomizer_code || '',
      ...specs.flatMap(s => [s.solution_code || '', s.randomizer_code || ''])
    ].join('\n'));
    await py.runPythonAsync(`
import ast, io, json, tokenize, sys, builtins, types
_detective_code = ${JSON.stringify(code)}
_detective_tree = ast.parse(_detective_code)
_detective_has_input_call = any(isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'input' for n in ast.walk(_detective_tree))
for _node in ast.walk(_detective_tree):
    _imports = [a.name for a in _node.names] if isinstance(_node, ast.Import) else [_node.module or ''] if isinstance(_node, ast.ImportFrom) else []
    if any(n.split('.')[0] in ('js', 'idegui') for n in _imports):
        raise RuntimeError('Code mit Browser-/GUI-Zugriff kann der isolierte Detektiv noch nicht untersuchen.')
_detective_names = sorted({n.id for n in ast.walk(_detective_tree) if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store)}
    | {n.name for n in ast.walk(_detective_tree) if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef))})
_detective_functions = sorted({n.name for n in _detective_tree.body if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef))})
_detective_printed_names = sorted({arg.id for node in ast.walk(_detective_tree)
    if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == 'print'
    for arg in node.args if isinstance(arg, ast.Name)})
_detective_solution_names = set()
_detective_solution_has_input_call = False
for _solution_source in ${JSON.stringify([task.solution_code || '', ...specs.map(s => s.solution_code || '')])}:
    try:
        _solution_tree = ast.parse(_solution_source)
    except SyntaxError:
        continue
    _detective_solution_has_input_call = _detective_solution_has_input_call or any(
        isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == 'input'
        for n in ast.walk(_solution_tree))
    _detective_solution_names.update(n.id for n in ast.walk(_solution_tree) if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store))
    _detective_solution_names.update(n.name for n in ast.walk(_solution_tree) if isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef)))
_detective_observed = []
_detective_function_observed = []
_detective_capture_output = False
_detective_output_frames = {}
_detective_outputs = []
def _detective_trace(frame, event, arg):
    if _detective_capture_output and frame.f_code.co_name == '<module>' and frame.f_code.co_filename in ('<code>', '<calculation>'):
        parent = frame.f_back
        executed_code = parent.f_locals.get('code') if parent else None
        source = 'user' if executed_code == _detective_active_code else 'solution' if parent and executed_code == parent.f_globals.get('solution_code') else None
        if event == 'call' and source:
            buffer = io.StringIO()
            _detective_output_frames[id(frame)] = (sys.stdout, buffer, source)
            sys.stdout = buffer
        elif event == 'return' and id(frame) in _detective_output_frames:
            previous, buffer, source = _detective_output_frames.pop(id(frame))
            sys.stdout = previous
            _detective_outputs.append({"source": source, "output": buffer.getvalue()})
    if event == 'return' and frame.f_code.co_name == '<module>' and frame.f_code.co_filename in ('<usercode>', '<code>', '<calculation>'):
        parent = frame.f_back
        executed_code = parent.f_locals.get('code', parent.f_globals.get('user_code')) if parent else None
        if executed_code == _detective_active_code:
            _detective_observed.append(sorted(k for k in frame.f_locals if not k.startswith('__')))
            _detective_function_observed.append(sorted(k for k, v in frame.f_locals.items() if isinstance(v, types.FunctionType) and v.__code__.co_filename in ('<usercode>', '<code>', '<calculation>')))
    return _detective_trace
def _detective_no_input(prompt=''):
    raise EOFError('Für diese Prüfung fehlen konfigurierte Eingabewerte.')
builtins.input = _detective_no_input

def _detective_constant_namespace(source):
    tree = ast.parse(source)
    assignments = []
    pure_nodes = (ast.Constant, ast.Name, ast.Load, ast.Store, ast.List, ast.Tuple, ast.Dict,
                  ast.Subscript, ast.Slice, ast.UnaryOp, ast.UAdd, ast.USub)
    for statement in tree.body:
        if isinstance(statement, ast.Expr) and isinstance(statement.value, ast.Call):
            call = statement.value
            if isinstance(call.func, ast.Name) and call.func.id == 'print' and all(
                isinstance(node, pure_nodes) for argument in call.args for node in ast.walk(argument)
            ) and not call.keywords:
                continue
        if isinstance(statement, ast.Expr) and isinstance(statement.value, ast.Constant):
            continue
        if not isinstance(statement, ast.Assign) or not all(isinstance(target, ast.Name) for target in statement.targets):
            return {"reason": "Der Code enthält mehr als feste Datenzuweisungen und einfache Ausgaben."}
        if any(target.id == 'print' for target in statement.targets) or not all(
            isinstance(node, pure_nodes) for node in ast.walk(statement.value)
        ):
            return {"reason": "Die Zuweisung ist nicht unabhängig von zusätzlichen Ausführungsbedingungen vergleichbar."}
        assignments.append(statement)
    namespace = {"__builtins__": {}}
    try:
        exec(compile(ast.Module(body=assignments, type_ignores=[]), "<detective-values>", "exec"), namespace)
    except (NameError, TypeError, ValueError, KeyError, IndexError, OverflowError) as error:
        return {"error": "Datenzuweisungen nicht vergleichbar: " + str(error)}
    return {"namespace": namespace}
`);
    const frozen = new Map();
    let activeType = '';
    let baseline = true;
    const runtime = {
      runPythonAsync: async source => {
        await py.runPythonAsync('sys.settrace(_detective_trace)');
        try {
          const value = await py.runPythonAsync(source);
          if (baseline && activeType === 'intelligent') {
            const raw = JSON.parse(value);
            if (raw.some(r => r.error || r.solution?.error)) throw new Error('Generator oder Musterlösung konnte nicht ausgeführt werden.');
            frozen.set(activeType, raw.map(r => r.values));
          }
          return value;
        } finally {
          await py.runPythonAsync('sys.settrace(None)');
        }
      }
    };
    async function evaluate(candidate) {
      await py.runPythonAsync(`_detective_active_code = ${JSON.stringify(candidate)}`);
      const results = [];
      for (const [type, cases] of Object.entries(groups)) {
        activeType = type;
        await py.runPythonAsync(`_detective_capture_output = ${type === 'intelligent' && cases.some(spec => spec.mode === 'vars') ? 'True' : 'False'}`);
        if (type === 'code_check') results.push(...runCodeCheck(candidate, cases));
        if (type === 'output') results.push(...await runOutputTests(runtime, candidate, cases, task));
        if (type === 'variable') results.push(...await runVariableTests(runtime, candidate, cases));
        if (type === 'function') results.push(...await runFunctionTests(runtime, candidate, cases));
        if (type === 'intelligent') results.push(...await runIntelligentTests(
          runtime, candidate, cases, task.solution_code, task.randomizer_code,
          baseline ? null : frozen.get(type)
        ));
      }
      if (!results.length) throw new Error('Die Prüfung lieferte keine Ergebnisse.');
      if (results.some(r => (r.error && /Musterloesung|Musterlösung|Randomizer|Solution Code|Failed to parse|fehlt \(erforderlich|not in values dict|fehlen konfigurierte Eingabewerte/.test(r.error))
        || String(r.expected).startsWith('Invalid regex:'))) {
        throw new Error('Die Prüfkonfiguration konnte nicht vollständig ausgeführt werden.');
      }
      return results;
    }
    const original = await evaluate(code);
    baseline = false;
    const capturedOutputs = JSON.parse(await py.runPythonAsync('json.dumps(_detective_outputs)'));
    const names = JSON.parse(await py.runPythonAsync('json.dumps(_detective_names)'));
    const functions = JSON.parse(await py.runPythonAsync('json.dumps(sorted(set().union(*_detective_function_observed)) if _detective_function_observed else _detective_functions)'));
    const observed = JSON.parse(await py.runPythonAsync('json.dumps(_detective_observed)'));
    const printedNames = JSON.parse(await py.runPythonAsync('json.dumps(_detective_printed_names)'));
    const solutionNames = JSON.parse(await py.runPythonAsync('json.dumps(sorted(_detective_solution_names))'));
    const failedRules = original.filter(r => r.type === 'code_check' && !r.passed && ['AND', 'OR'].includes(r.operator));
    // Solution names are candidates only when explicitly referenced by a failed rule.
    const ruleNames = solutionNames.filter(name => /^[A-Za-z_]\w*$/.test(name)
      && failedRules.some(rule => rule.keywordResults.some(keyword => !keyword.found
        && new RegExp(`(^|[^A-Za-z0-9_])${name}(?=$|[^A-Za-z0-9_])`).test(keyword.keyword))));
    const functionTargets = [...new Set(specs.flatMap(s => s.type === 'function'
      ? [s.function_name, ...(s.test_cases || []).map(c => c.function_name)].filter(Boolean)
      : s.type === 'intelligent' && s.mode === 'function' ? [s.function?.name].filter(Boolean) : []))];
    const expected = [...new Set([...functionTargets, ...specs.flatMap(s => s.type === 'variable'
      ? (s.expected_var_names || Object.keys(s.expected_vars || {}))
      : s.type === 'intelligent' && s.mode === 'vars' ? s.outputs || [] : [])])];
    const emptyValueTargets = [...new Set(original.flatMap(result => (result.matchDetails || [])
      .filter(detail => !detail.matches
        && typeof detail.actual === 'string' && detail.actual.trim() === ''
        && typeof detail.expected === 'string' && detail.expected.trim() !== ''
        && names.includes(detail.varName || detail.name))
      .map(detail => detail.varName || detail.name)))];
    const hasExpectedOutput = value => Array.isArray(value)
      ? value.some(item => typeof item === 'string' && item.trim() !== '')
      : typeof value === 'string' && value.trim() !== '';
    const emptyCheckedOutput = original.some(result => result.type === 'output' && !result.passed && !result.error
      && typeof result.output === 'string' && result.output.trim() === ''
      && hasExpectedOutput(result.expected));
    const userOutputs = capturedOutputs.filter(entry => entry.source === 'user');
    const solutionOutputs = capturedOutputs.filter(entry => entry.source === 'solution');
    const emptyIntelligentOutput = original.some(result => result.type === 'intelligent' && result.mode === 'vars' && !result.passed)
      && !original.some(result => result.type === 'intelligent' && result.error)
      && userOutputs.length > 0 && userOutputs.every(entry => entry.output.trim() === '')
      && solutionOutputs.some(entry => entry.output.trim() !== '');
    const emptyOutput = emptyCheckedOutput || emptyIntelligentOutput;
    const emptyOutputTargets = emptyCheckedOutput ? printedNames : [];
    const absent = expected.filter(name => functionTargets.includes(name)
      ? !functions.includes(name) && original.some(r => !r.passed
        && (String(r.error).includes(name) || r.functionName === name && /not callable/.test(r.error || '')))
      : observed.length && observed.every(keys => !keys.includes(name)));
    const targets = [...new Set([...absent, ...ruleNames.filter(name => !names.includes(name))])];
    const findings = [];
    let workingCode = code;
    let current = original;
    let mapped = false;
    const mappedTargets = new Set();
    let attempts = 0;
    const usesIntelligentVars = specs.some(spec => spec.type === 'intelligent' && spec.mode === 'vars');
    const generatedInputValues = [...frozen.values()].flat().some(values => values
      && Object.keys(values).some(name => /^INPUT(?:_|#)?\d+$/i.test(name)));
    if (usesIntelligentVars && generatedInputValues
      && original.some(result => result.type === 'intelligent' && !result.passed)
      && JSON.parse(await py.runPythonAsync('json.dumps(_detective_solution_has_input_call)'))
      && !JSON.parse(await py.runPythonAsync('json.dumps(_detective_has_input_call)'))) {
      findings.push('Das Programm soll Benutzereingaben einlesen. Dein Code enthält bisher keinen Aufruf von input().');
    }
    for (const target of [...new Set([...targets, ...emptyValueTargets, ...emptyOutputTargets])]) {
      const pool = (functionTargets.includes(target) ? functions : names).filter(name => name !== target && !expected.includes(name));
      const similar = pool.filter(name => detectiveNameDistance(name, target) <= (target.length >= 7 ? 2 : 1));
      const candidates = emptyValueTargets.includes(target)
        ? similar.length === 1 ? similar : []
        : similar.length === 1 ? similar : similar.length ? [] : pool;
      if (candidates.length > 4 || attempts >= 8) continue;
      const verified = [];
      for (const sourceName of candidates) {
        if (++attempts > 8) break;
        const candidate = await py.runPythonAsync(`
_detective_tokens = list(tokenize.generate_tokens(io.StringIO(${JSON.stringify(workingCode)}).readline))
tokenize.untokenize([t._replace(string=${JSON.stringify(target)}) if t.type == tokenize.NAME and t.string == ${JSON.stringify(sourceName)} else t for t in _detective_tokens])
`);
        const result = await evaluate(candidate);
        const preserved = current.every((r, i) => !r.passed || result[i]?.passed);
        const removedMissing = current.some(r => !r.passed && String(r.error).includes(target) && /not found/.test(r.error));
        const exposedResults = removedMissing && result.every(r => !r.error
          || /not found/.test(r.error) && targets.some(other => other !== target && String(r.error).includes(other)));
        const improvedEmptyValue = emptyValueTargets.includes(target) && current.some((r, index) =>
          !r.passed
          && (r.matchDetails || []).some(detail => !detail.matches
            && (detail.varName || detail.name) === target
            && typeof detail.actual === 'string' && detail.actual.trim() === ''
            && typeof detail.expected === 'string' && detail.expected.trim() !== '')
          && result[index]?.passed);
        const improvedEmptyOutput = emptyOutputTargets.includes(target) && current.some((r, index) =>
          r.type === 'output' && !r.passed && typeof r.output === 'string' && r.output.trim() === ''
          && result[index]?.type === 'output' && result[index].passed);
        const improvedRule = ruleNames.includes(target) && result.some((r, i) => r.type === 'code_check' && r.passed && !current[i].passed);
        const strongMatch = result.some(r => r.type !== 'output' && r.type !== 'code_check' && r.passed)
          && result.filter(r => r.type !== 'output' && r.type !== 'code_check').every(r => r.passed);
        if (preserved && (similar.length === 1
          ? exposedResults || improvedRule || improvedEmptyValue || improvedEmptyOutput
          : (exposedResults && strongMatch) || improvedRule && result.every(r => r.passed))) {
          verified.push({ sourceName, candidate, result });
        }
      }
      if (verified.length === 1) {
        const { sourceName, candidate, result } = verified[0];
        findings.push(emptyOutputTargets.includes(target)
          ? `Die Ausgabe ist leer. Die ausgegebene Variable „${target}“ bleibt leer; die ähnlich lautende Variable „${sourceName}“ ist vorhanden – möglicherweise ein Tippfehler.`
          : emptyValueTargets.includes(target)
            ? `Die Prüfvariable „${target}“ ist leer, aber es existiert die ähnlich lautende Variable „${sourceName}“ – möglicherweise ein Tippfehler.`
          : `Die Prüfung erwartet „${target}“; vorhanden ist „${sourceName}“. Möglicherweise ist eine andere Benennung gemeint.`);
        workingCode = candidate;
        current = result;
        mapped = true;
        mappedTargets.add(target);
      }
    }
    if (emptyOutput && !findings.some(message => message.startsWith('Die Ausgabe ist leer.'))) {
      findings.unshift('Die Ausgabe ist leer.');
    }
    let outputHint = null;
    if (current.some(r => r.type === 'output' && !r.passed) && !current.some(r => r.error)) {
      const candidates = JSON.parse(await py.runPythonAsync(detectiveOutputCandidates(workingCode)));
      for (const candidate of candidates) {
        if (++attempts > 16) break;
        const result = await evaluate(candidate.code);
        if (result.length === current.length && current.every((r, i) => !r.passed || result[i].passed)
          && result.filter(r => r.type === 'output').every(r => r.passed)) {
          outputHint = candidate.message;
          break;
        }
      }
    }
    let ruleValues = [];
    // Only explicit rule names and fixed, self-contained data can supply extra value evidence.
    if (task.solution_code && ruleNames.length && !mapped && !current.some(r => r.error)
      && Object.keys(groups).every(type => type === 'code_check' || type === 'output')
      && !(groups.output || []).some(spec => spec.mock_inputs?.length) && !task.randomizer_code) {
      const comparison = JSON.parse(await py.runPythonAsync(`
_detective_user_data = _detective_constant_namespace(${JSON.stringify(code)})
_detective_solution_data = _detective_constant_namespace(${JSON.stringify(task.solution_code)})
_detective_pairs = []
if "namespace" in _detective_user_data and "namespace" in _detective_solution_data:
    for name in ${JSON.stringify(ruleNames)}:
        if name in _detective_user_data["namespace"] and name in _detective_solution_data["namespace"]:
            actual = _detective_user_data["namespace"][name]
            expected_value = _detective_solution_data["namespace"][name]
            try:
                json.dumps([actual, expected_value], allow_nan=False)
            except (TypeError, ValueError):
                continue
            _detective_pairs.append({"name": name, "actual": actual, "expected": expected_value})
json.dumps({"values": _detective_pairs, "error": _detective_user_data.get("error") or _detective_solution_data.get("error")})
`));
      if (comparison.error) throw new Error(`Der ergänzende Wertvergleich konnte nicht ausgeführt werden: ${comparison.error}`);
      ruleValues = comparison.values.map(value => ({ ...value, matches: compareValues(value.actual, value.expected, true) }));
    }
    const report = summarizeDetective(current, {
      names, functions, absent: targets.filter(target => !mappedTargets.has(target)),
      functionTargets, findings, outputHint, specs, mapped, ruleValues
    });
    self.postMessage({ ok: true, report });
  } catch (error) {
    self.postMessage({ ok: false, error: String(error.message || error) });
  }
}

export function detectiveRepairPasses(original, candidate) {
  const functional = candidate.filter(r => r.type !== 'code_check');
  return functional.length > 0 && candidate.length === original.length
    && functional.every(r => r.passed)
    && original.every((r, i) => !r.passed || candidate[i].passed);
}

export function detectiveNameDistance(a, b) {
  const row = Array.from({ length: b.length + 1 }, (_, i) => i);
  for (let i = 1; i <= a.length; i++) {
    let prev = row[0];
    row[0] = i;
    for (let j = 1; j <= b.length; j++) {
      const old = row[j];
      row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + (a[i - 1] === b[j - 1] ? 0 : 1));
      prev = old;
    }
  }
  return row[b.length];
}

export function detectiveOutputCandidates(code) {
  return `
_ds = ${JSON.stringify(code)}
_dt = ast.parse(_ds)
_dc = []
_dl = _ds.splitlines(keepends=True)
def _offset(line, column):
    return sum(len(s) for s in _dl[:line-1]) + len(_dl[line-1].encode('utf-8')[:column].decode('utf-8'))
def _span(node):
    return _offset(node.lineno, node.col_offset), _offset(node.end_lineno, node.end_col_offset)
def _plain_print(node):
    return isinstance(node, ast.Expr) and isinstance(node.value, ast.Call) and isinstance(node.value.func, ast.Name) and node.value.func.id == 'print'
# Only adjacent, top-level prints of already computed names/constants; never reorder calculations.
for _i in range(len(_dt.body) - 1):
    if len(_dc) >= 6:
        break
    _a, _b = _dt.body[_i:_i+2]
    if _plain_print(_a) and _plain_print(_b) and all(isinstance(v, (ast.Name, ast.Constant)) for n in (_a, _b) for v in n.value.args) and not _a.value.keywords and not _b.value.keywords:
        _as, _ae = _span(_a)
        _bs, _be = _span(_b)
        _candidate = _ds[:_as] + _ds[_bs:_be] + _ds[_ae:_bs] + _ds[_as:_ae] + _ds[_be:]
        _dc.append({"code": _candidate, "message": "Ausgabereihenfolge: Zwei benachbarte Ausgabezeilen stehen in einer anderen Reihenfolge als verlangt."})
for _node in ast.walk(_dt):
    if len(_dc) >= 6:
        break
    if not _plain_print(_node):
        continue
    for _kw in _node.value.keywords:
        if _kw.arg not in ('sep', 'end') or not isinstance(_kw.value, ast.Constant):
            continue
        for _replacement in ([' ', '', '\\n'] if _kw.arg == 'sep' else ['\\n', '']):
            if len(_dc) >= 6:
                break
            if _kw.value.value == _replacement:
                continue
            _ks, _ke = _span(_kw.value)
            _candidate = _ds[:_ks] + repr(_replacement) + _ds[_ke:]
            _dc.append({"code": _candidate, "message": "Ausgabeformat: Prüfe sep/end deiner print-Ausgaben; Trennzeichen oder Zeilenumbrüche weichen von der Vorgabe ab."})
for _node in _dt.body:
    if len(_dc) >= 8:
        break
    if not _plain_print(_node) or len(_node.value.args) != 2 or _node.value.keywords:
        continue
    _label, _value = _node.value.args
    if not isinstance(_label, ast.Constant) or not isinstance(_label.value, str) or not isinstance(_value, (ast.Name, ast.Subscript)):
        continue
    _ps, _pe = _span(_node)
    _vs, _ve = _span(_value)
    _dc.append({"code": _ds[:_ps] + "print(" + _ds[_vs:_ve] + ")" + _ds[_pe:], "message": "Ausgabeformat: Die Ausgabe enthält eine zusätzliche Beschriftung, die nicht zur Vorgabe passt."})
json.dumps(_dc[:8])
`;
}

export function runDetectiveWorker(task, code, runtimeSource, signal) {
  const helpers = [summarizeDetective, detectiveValueDifference, detectiveRuntimeError, detectiveRepairPasses, detectiveNameDistance, detectiveOutputCandidates];
  const source = `${runtimeSource}\n${helpers.map(fn => fn.toString()).join('\n')}\nself.onmessage = ${detectiveWorker.toString()};`;
  const url = URL.createObjectURL(new Blob([source], { type: 'text/javascript' }));
  let worker;
  try {
    worker = new Worker(url);
  } catch (error) {
    URL.revokeObjectURL(url);
    throw error;
  }
  return new Promise((resolve, reject) => {
    let settled = false;
    const finish = (error, report) => {
      if (settled) return;
      settled = true;
      clearTimeout(timer);
      signal?.removeEventListener('abort', abort);
      worker.terminate();
      URL.revokeObjectURL(url);
      if (error) reject(error); else resolve(report);
    };
    const abort = () => finish(new DOMException('Diagnose abgebrochen.', 'AbortError'));
    const timer = setTimeout(() => finish(new Error('Die Diagnose dauerte zu lange. Es wurde keine Nutzung verbraucht.')), 60000);
    signal?.addEventListener('abort', abort, { once: true });
    worker.onmessage = ({ data }) => finish(data.ok ? null : new Error(data.error), data.report);
    worker.onerror = event => finish(new Error(event.message || 'Der Diagnose-Worker konnte nicht geladen werden.'));
    if (signal?.aborted) abort();
    else worker.postMessage({ task, code, indexURL: new URL('pyodide/', document.baseURI).href });
  });
}

export function installSolutionDetective({ getTask, getEditor, isSolution, runtimeSource, isAdminTest = () => false }) {
  const checkButton = document.getElementById('check-btn');
  if (!checkButton) return null;
  const button = document.createElement('button');
  button.id = 'detective-btn';
  button.type = 'button';
  button.hidden = true;
  checkButton.after(button);
  const historyButton = document.createElement('button');
  historyButton.type = 'button';
  historyButton.id = 'detective-history-btn';
  historyButton.textContent = '🔎 Bisherige Analysen';
  historyButton.hidden = true;
  button.after(historyButton);
  const style = document.createElement('style');
  style.textContent = `
    #detective-dialog { width:min(600px,calc(100vw - 24px)); max-height:calc(100vh - 24px); max-height:calc(100dvh - 24px);
      box-sizing:border-box; overflow:auto; background:var(--bg-primary,#fff);
      color:var(--text-primary,#222); border:1px solid #888; border-radius:10px; padding:16px; }
    #detective-dialog::backdrop { background:#0008; }
    #detective-dialog h3 { margin:0 0 12px; }
    #detective-dialog p { white-space:pre-wrap; overflow-wrap:anywhere; }
    #detective-dialog article { border-top:1px solid #8886; margin-top:12px; padding-top:8px; }
    #detective-dialog button { margin:4px 8px 4px 0; }
    #detective-btn[hidden] { display:none !important; }
    #detective-btn:disabled { opacity:0.4; cursor:not-allowed; }
    #detective-history-btn[hidden] { display:none !important; }
  `;
  document.head.append(style);
  const dialog = document.createElement('dialog');
  dialog.id = 'detective-dialog';
  dialog.setAttribute('aria-labelledby', 'detective-title');
  const title = document.createElement('h3');
  title.id = 'detective-title';
  title.textContent = '🔎 Lösungsdetektiv';
  const status = document.createElement('p');
  status.setAttribute('role', 'status');
  const close = document.createElement('button');
  close.type = 'button';
  close.textContent = 'Schließen';
  const history = document.createElement('div');
  dialog.append(title, status, history, close);
  document.body.append(dialog);
  close.addEventListener('click', () => dialog.close());
  let taskId = null;
  let data = null;
  let error = '';
  let executed = null;
  let running = null;
  let editorSubscription = null;
  let subscribedEditor = null;
  let requestVersion = 0;
  dialog.addEventListener('close', () => running?.abort());
  const apiURL = new URL('../api/user_tasks/detective.php', document.baseURI);
  const currentCode = () => getEditor()?.getValue() || '';
  function draw() {
    const task = getTask();
    const supported = detectiveSupported(task);
    button.hidden = !supported || isSolution();
    const checkedCode = !!executed && !!task && executed.taskId === task.id && executed.code === currentCode();
    const ready = checkedCode && !executed.passed;
    const used = data ? 2 - data.remaining : null;
    const count = data?.unlimited ? '∞' : `${used ?? '…'}/2`;
    button.textContent = `🔎 Detektiv (${count})`;
    button.disabled = !!running || !ready || !data;
    historyButton.hidden = button.hidden || !data?.reports?.length;
    button.title = executed?.passed && executed.code === currentCode()
      ? 'Dein Code besteht die Prüfung.'
      : 'Nach Run oder Check des aktuellen Codestands verfügbar. Zwei Einschätzungen pro Aufgabe.';
    status.textContent = error || (running ? 'Untersuche eine isolierte Kopie …'
      : !data ? 'Lade Detektiv-Nutzungen …'
        : checkedCode && executed.passed ? 'Dein aktueller Code besteht die Prüfung. Eine Diagnose ist nicht erforderlich.'
          : !ready ? 'Bitte zuerst den aktuellen Code mit Run ausführen oder mit Check prüfen.'
          : data.unlimited ? 'Admin-Test · unbegrenzt'
            : `${used} von 2 Analysen verbraucht`);
    history.replaceChildren();
    for (const [index, entry] of [...(data?.reports || [])].reverse().entries()) {
      const article = document.createElement(index === 0 ? 'article' : 'details');
      if (index > 0) {
        const heading = document.createElement('summary');
        heading.textContent = 'Frühere Analyse';
        article.append(heading);
      }
      for (const text of entry.report.messages) {
        const p = document.createElement('p');
        p.textContent = text;
        article.append(p);
      }
      history.append(article);
    }
  }
  async function request(method, payload) {
    const url = new URL(apiURL);
    payload = { ...payload, admin_test: isAdminTest() ? 1 : 0 };
    if (method === 'GET') {
      url.searchParams.set('task_id', String(payload.task_id));
      url.searchParams.set('admin_test', String(payload.admin_test));
    }
    const response = await fetch(url, {
      method, credentials: 'same-origin',
      ...(method === 'POST' ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) } : {})
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || 'Der Lösungsdetektiv ist nicht verfügbar.');
    return result;
  }
  function refresh() {
    const editor = getEditor();
    if (editor && editor !== subscribedEditor) {
      editorSubscription?.dispose();
      subscribedEditor = editor;
      editorSubscription = editor.onDidChangeModelContent(() => {
        running?.abort();
        draw();
      });
    }
    const task = getTask();
    if (task?.id !== taskId) {
      running?.abort();
      running = null;
      taskId = task?.id ?? null;
      data = null;
      error = '';
      executed = null;
      dialog.close();
      const version = ++requestVersion;
      if (detectiveSupported(task)) {
        request('GET', { task_id: task.id }).then(result => {
          if (version !== requestVersion) return;
          data = result;
          draw();
        }).catch(err => {
          if (version !== requestVersion) return;
          console.error('[Detective]', err);
          error = err.message;
          draw();
        });
      }
    }
    draw();
  }
  historyButton.addEventListener('click', () => {
    draw();
    dialog.showModal();
  });
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    dialog.showModal();
    const task = getTask();
    const code = currentCode();
    const version = requestVersion;
    const controller = new AbortController();
    running = controller;
    error = '';
    draw();
    try {
      // Include configuration so a changed task never replays an obsolete diagnosis.
      const bytes = new TextEncoder().encode(JSON.stringify(['detective6', code, task.test_cases, task.solution_code, task.randomizer_code]));
      const hash = await crypto.subtle.digest('SHA-256', bytes);
      const fingerprint = Array.from(new Uint8Array(hash), byte => byte.toString(16).padStart(2, '0')).join('');
      const saved = data.reports.find(entry => entry.fingerprint === fingerprint);
      if (saved) {
        data = { ...data, reports: [...data.reports.filter(entry => entry !== saved), saved] };
      } else {
        if (!data.unlimited && data.remaining <= 0) {
          error = 'Beide Analysen sind verbraucht. Bisherige Analysen bleiben lesbar.';
          return;
        }
        const report = await runDetectiveWorker(task, code, runtimeSource(), controller.signal);
        if (controller.signal.aborted || version !== requestVersion || code !== currentCode()) return;
        const result = await request('POST', { task_id: task.id, fingerprint, report });
        if (version !== requestVersion) return;
        data = result.unlimited
          ? { ...result, reports: [...data.reports, { fingerprint, report: result.report }] }
          : result;
      }
    } catch (err) {
      if (err.name !== 'AbortError' && version === requestVersion) {
        console.error('[Detective]', err);
        error = err.message;
      }
    } finally {
      if (running === controller) running = null;
      draw();
    }
  });
  const controller = {
    refresh,
    started() {
      running?.abort();
      executed = null;
      draw();
    },
    completed(id, code, passed = false) {
      if (!id || getTask()?.id !== id) return;
      executed = { taskId: id, code, passed };
      refresh();
    }
  };
  window.addEventListener('pagehide', () => running?.abort());
  refresh();
  return controller;
}
