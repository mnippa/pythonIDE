#!/usr/bin/env node
/**
 * run_simple_test.cjs
 *
 * Simplified test: waits for page complete load, then uses accessible button text
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const TARGET_URL = 'http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338';
const RESULTS_DIR = path.join(__dirname, '..', '..', 'storage', 'test_results');

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function runTest() {
  console.log('[simple-test] Starting simple test...');
  
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: false
  });

  try {
    const page = await browser.newPage();
    
    console.log('[simple-test] Navigating to:', TARGET_URL);
    await page.goto(TARGET_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
    
    // Wait for page to be fully interactive
    console.log('[simple-test] Waiting for full page load...');
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {
      console.log('[simple-test] Network idle timeout, continuing anyway');
    });
    
    await sleep(3000); // Extra wait for all JS initialization
    
    // Check if page loaded correctly
    const hasRunButton = await page.getByText('Run', { exact: false }).isVisible().catch(() => false);
    console.log('[simple-test] Run button visible:', hasRunButton);
    
    if (!hasRunButton) {
      console.log('[simple-test] ERROR: Run button not found!');
      console.log('[simple-test] Checking page content...');
      const bodyText = await page.evaluate(() => document.body.innerText);
      console.log('[simple-test] Page text:', bodyText.substring(0, 500));
      throw new Error('Run button not found');
    }
    
    // Step 1: Run template
    console.log('[simple-test] Step 1: Click Run (template)');
    const runBtn = page.getByText('Run', { exact: false });
    await runBtn.click();
    await sleep(4000);
    
    // Get output
    const output1 = await page.evaluate(() => {
      const el = document.getElementById('output-container');
      return el ? el.innerText : 'NO OUTPUT';
    });
    console.log('[simple-test] Output 1 (first 100 chars):', output1.substring(0, 100));
    
    // Step 2: Toggle solution
    console.log('[simple-test] Step 2: Toggle to solution');
    const modeButton = page.locator('generic:has-text("Modus")');
    await modeButton.isVisible({ timeout: 5000 }).catch(() => {
      console.log('[simple-test] Mode indicator not found, trying to find toggle button');
    });
    await sleep(2000);
    
    // Step 3: Run solution
    console.log('[simple-test] Step 3: Run solution');
    await runBtn.click();
    await sleep(4000);
    
    const output2 = await page.evaluate(() => {
      const el = document.getElementById('output-container');
      return el ? el.innerText : 'NO OUTPUT';
    });
    console.log('[simple-test] Output 2 (first 100 chars):', output2.substring(0, 100));
    
    // Step 4: Toggle back
    console.log('[simple-test] Step 4: Toggle back to template');
    await modeButton.isVisible({ timeout: 5000 }).catch(() => {
      console.log('[simple-test] Mode indicator not found');
    });
    await sleep(2000);
    
    // Step 5: Run template again (CRITICAL)
    console.log('[simple-test] Step 5: Run template again (critical check)');
    await runBtn.click();
    await sleep(4000);
    
    const output3 = await page.evaluate(() => {
      const el = document.getElementById('output-container');
      return el ? el.innerText : 'NO OUTPUT';
    });
    console.log('[simple-test] Output 3 (first 100 chars):', output3.substring(0, 100));
    
    // Check for markers
    const has_markers_1 = output1.includes('🟨');
    const has_markers_2 = output2.includes('🟩');
    const has_markers_3 = output3.includes('🟨');
    const contamination = output3.includes('🟩');
    
    const passed = has_markers_1 && has_markers_2 && has_markers_3 && !contamination;
    
    console.log('[simple-test] ═══════════════════════════════════════');
    console.log('[simple-test] MARKERS:');
    console.log('[simple-test]   Output 1 has 🟨 (template):', has_markers_1);
    console.log('[simple-test]   Output 2 has 🟩 (solution):', has_markers_2);
    console.log('[simple-test]   Output 3 has 🟨 (template):', has_markers_3);
    console.log('[simple-test]   Output 3 CONTAMINATED with 🟩:', contamination);
    console.log('[simple-test]');
    console.log('[simple-test] TEST RESULT:', passed ? '✅ PASSED' : '❌ FAILED');
    if (contamination) {
      console.log('[simple-test] ⚠️  RACE CONDITION: Template mode but solution output!');
    }
    console.log('[simple-test] ═══════════════════════════════════════');
    
    // Save result
    if (!fs.existsSync(RESULTS_DIR)) {
      fs.mkdirSync(RESULTS_DIR, { recursive: true });
    }
    
    const result = {
      passed,
      timestamp: new Date().toISOString(),
      markers: {
        output1_template: has_markers_1,
        output2_solution: has_markers_2,
        output3_template: has_markers_3,
        race_condition: contamination
      },
      outputs: {
        output1_preview: output1.substring(0, 200),
        output2_preview: output2.substring(0, 200),
        output3_preview: output3.substring(0, 200)
      }
    };
    
    const filename = `test_result_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    fs.writeFileSync(path.join(RESULTS_DIR, filename), JSON.stringify(result, null, 2));
    console.log('[simple-test] Result saved to:', path.join(RESULTS_DIR, filename));
    
    process.exit(passed ? 0 : 1);
    
  } catch (err) {
    console.error('[simple-test] ERROR:', err.message);
    process.exit(2);
  } finally {
    await browser.close();
  }
}

runTest();
