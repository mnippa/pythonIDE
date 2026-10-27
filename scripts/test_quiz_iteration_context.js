const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'quiz-renderer.js'), 'utf8');

function setup() {
  const window = {
    testMode: true,
    assignmentState: { taskUserAnswers: {}, taskIterations: {} }
  };
  vm.runInNewContext(source, {
    window, console, btoa: value => Buffer.from(value, 'binary').toString('base64'),
    unescape, encodeURIComponent
  });
  return { window, renderer: window.QuizRenderer };
}

function task(context = true) {
  return {
    id: 561, task_type: 'code_random_complex', max_iterations: 8,
    randomizer_code: 'zahl = 42',
    variable_overrides: JSON.stringify([{
      iteration_context: context,
      inputs: { zahl: '<random>' }
    }])
  };
}

function decodeHistory(namespace) {
  const encoded = namespace.match(/b64decode\("([^"]*)"\)/)[1];
  return JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
}

test('existing tasks keep exactly the empty namespace unless explicitly opted in', () => {
  const { renderer } = setup();
  for (const overrides of [null, '{}', '[]', '[{"inputs":{"zahl":"<random>"}}]',
    '[{"iteration_context":false}]', '[{"iteration_context":"true"}]']) {
    assert.equal(renderer.getRandomizerNamespace({ variable_overrides: overrides }), '{}');
  }
});

test('opted-in history is isolated, UTF-8 safe, and does not include answers', () => {
  const { renderer } = setup();
  const entries = [{ iteration: 1, inputs: { zahl: 'Größe', wert: 2 }, answer: 'secret' }];
  const history = decodeHistory(renderer.getRandomizerNamespace(task(), entries));
  assert.deepEqual(history, [{ zahl: 'Größe', wert: 2 }]);
  assert.equal(renderer.getRandomizerNamespace(task(false), entries), '{}');
  assert.deepEqual(decodeHistory(renderer.getRandomizerNamespace(task(), [])), []);
});

test('missing history and legacy placeholders fail explicitly only for opted-in tasks', () => {
  const { renderer, window } = setup();
  window.assignmentState.taskUserAnswers[561] = { current_iteration: 3 };
  renderer.getCurrentIteration = () => 3;
  assert.throws(() => renderer.getRandomizerNamespace(task()), /Iterationswerte fehlen/);
  assert.throws(() => renderer.getRandomizerNamespace(task(), [{ inputs: null }]), /Ungültige/);
  assert.equal(renderer.getRandomizerNamespace(task(false)), '{}');
});

test('batch generation supplies only earlier inputs and retains existing persistence shape', async () => {
  const { renderer, window } = setup();
  renderer.getMaxIterations = () => 8;
  renderer.getCurrentIteration = () => 1;
  let executions = 0;
  renderer.getPyodideOrThrow = async () => ({
    runPythonAsync: async python => {
      const history = decodeHistory(python);
      assert.deepEqual(history, Array.from({ length: executions }, (_, index) => ({ zahl: index + 1 })));
      executions++;
      return { toJs: () => ({ zahl: executions, __previous_inputs: history, random: {} }) };
    }
  });
  const values = await renderer.initAllIterations(task());
  assert.equal(executions, 8);
  assert.equal(values.length, 8);
  assert.deepEqual(JSON.parse(JSON.stringify(values[7])), { iteration: 8, inputs: { zahl: 8 }, answer: null });
  assert.equal(window.assignmentState.taskUserAnswers[561].iteration_values, values);
});

test('existing randomized tasks execute with the unchanged namespace on every iteration', async () => {
  const { renderer } = setup();
  renderer.getMaxIterations = () => 8;
  renderer.getCurrentIteration = () => 1;
  let executions = 0;
  renderer.getPyodideOrThrow = async () => ({
    runPythonAsync: async python => {
      assert.match(python, /__randomizer_namespace = \{\}\n/);
      assert.ok(!python.includes('__previous_inputs'));
      executions++;
      return { toJs: () => ({ zahl: 42 }) };
    }
  });
  const values = await renderer.initAllIterations(task(false));
  assert.equal(executions, 8);
  assert.ok(values.every(entry => entry.inputs.zahl === 42));
});

test('single-iteration fallback uses stored previous inputs for opted-in tasks', async () => {
  const { renderer, window } = setup();
  renderer.getCurrentIteration = () => 3;
  window.assignmentState.taskUserAnswers[561] = {
    iteration_values: [
      { iteration: 1, inputs: { zahl: 1 } },
      { iteration: 2, inputs: { zahl: 2 } }
    ]
  };
  renderer.getPyodideOrThrow = async () => ({
    runPythonAsync: async python => {
      assert.deepEqual(decodeHistory(python), [{ zahl: 1 }, { zahl: 2 }]);
      return { toJs: () => ({ zahl: 3 }) };
    }
  });
  const values = await renderer.ensureGeneratedValues(task());
  assert.deepEqual(JSON.parse(JSON.stringify(values)), { zahl: 3 });
});
