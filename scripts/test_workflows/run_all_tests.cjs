#!/usr/bin/env node

// Wrapper to run Playwright tests without shell escaping issues

const { spawn } = require('child_process');
const path = require('path');

const tests = [
  {
    name: 'Basic Toggle Test',
    script: 'playwright_toggle_workflow.cjs',
    env: {
      TARGET_URL: 'http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338'
    }
  },
  {
    name: 'Advanced Toggle Test (with code modification)',
    script: 'playwright_toggle_advanced.cjs',
    env: {
      TARGET_URL: 'http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338'
    }
  }
];

async function runTest(test) {
  return new Promise((resolve) => {
    console.log(`\n${'='.repeat(70)}`);
    console.log(`Running: ${test.name}`);
    console.log(`${'='.repeat(70)}\n`);

    const scriptPath = path.join(__dirname, test.script);
    const proc = spawn('node', [scriptPath], {
      stdio: 'inherit',
      env: { ...process.env, ...test.env }
    });

    proc.on('exit', (code) => {
      resolve(code === 0);
    });

    proc.on('error', (err) => {
      console.error(`Error running ${test.name}:`, err);
      resolve(false);
    });
  });
}

async function runAll() {
  const results = [];
  for (const test of tests) {
    try {
      const passed = await runTest(test);
      results.push({ name: test.name, passed });
    } catch (err) {
      console.error(`Failed to run ${test.name}:`, err);
      results.push({ name: test.name, passed: false });
    }
  }

  console.log(`\n${'='.repeat(70)}`);
  console.log('TEST SUMMARY');
  console.log(`${'='.repeat(70)}`);
  results.forEach((r) => {
    const status = r.passed ? '✓ PASSED' : '✗ FAILED';
    console.log(`${status}: ${r.name}`);
  });
  console.log(`${'='.repeat(70)}\n`);

  const allPassed = results.every((r) => r.passed);
  process.exit(allPassed ? 0 : 1);
}

runAll();
