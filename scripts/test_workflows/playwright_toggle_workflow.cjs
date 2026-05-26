#!/usr/bin/env node
/**
 * playwright_toggle_workflow.cjs
 *
 * Tests the exact user workflow that previously failed with race conditions:
 * 1. Load assignment/task in template mode
 * 2. Run template (captures output with visible markers)
 * 3. Edit the code
 * 4. Save template
 * 5. Toggle to solution mode
 * 6. Run solution (should show different output)
 * 7. Toggle back to template mode
 * 8. Run template again (CRITICAL: must show original template output, not solution)
 * 9. Compare outputs to verify mode switching worked correctly
 *
 * This test is designed to catch the race condition where:
 * - Frontend UI shows template mode
 * - But Pyodide filesystem still has solution files
 * - Resulting in wrong code execution
 *
 * Environment:
 * - LOCAL: http://localhost/pythonIDEBeta (slower, different async timing)
 * - PRODUCTION: https://winglearning.hs-pforzheim.de/pythonIDEBeta (faster, exposes race)
 *
 * Usage:
 *   TARGET_URL="http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338" \
 *   node scripts/test_workflows/playwright_toggle_workflow.cjs
 *
 *   Or for production:
 *   TARGET_URL="https://winglearning.hs-pforzheim.de/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=38&task_id=325" \
 *   node scripts/test_workflows/playwright_toggle_workflow.cjs
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Config from environment or defaults
const TARGET_URL = process.env.TARGET_URL ||
  'http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338';

const RESULTS_DIR = process.env.RESULTS_DIR || path.join(__dirname, '..', '..', 'storage', 'test_results');

// Utility: Extract colored output for comparison
function extractTemplateMarkers(html) {
  // Look for template color markers (yellow squares: 🟨)
  const templatePattern = /🟨/g;
  // Solution color markers (green squares: 🟩)
  const solutionPattern = /🟩/g;
  
  const templateCount = (html.match(templatePattern) || []).length;
  const solutionCount = (html.match(solutionPattern) || []).length;
  
  return {
    hasTemplateMarkers: templateCount > 0,
    hasSolutionMarkers: solutionCount > 0,
    templateCount,
    solutionCount,
    output: html
  };
}

async function runTest() {
  console.log('[test] Starting Playwright toggle workflow test...');
  console.log('[test] Target URL:', TARGET_URL);
  
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: false,
    args: ['--disable-blink-features=AutomationControlled']
  });

  try {
    const page = await browser.newPage();
    
    // Step 1: Navigate to assignment
    console.log('[test] [1/9] Loading assignment editor...');
    await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000); // Wait for initialization
    
    // Step 2: Run template mode
    console.log('[test] [2/9] Running template code...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      // Fallback: find run button another way
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000); // Wait for Pyodide execution
    const templateRunOutput = await page.locator('#output, [id*="output"], .output').textContent();
    const templateMarkers1 = extractTemplateMarkers(templateRunOutput || '');
    console.log('[test] Template run 1 - Template markers:', templateMarkers1.hasTemplateMarkers);
    
    // Step 3-4: Edit and save (simulated - just ensure mode preserved)
    console.log('[test] [3/9] Simulating code edit...');
    await page.waitForTimeout(1000);
    
    // Step 5: Save template
    console.log('[test] [4/9] Saving template...');
    // Try to find save button
    const saveBtn = page.locator('button:has-text("Save"), button:has-text("Speichern")')
      .first();
    if (await saveBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await saveBtn.click();
      await page.waitForTimeout(2000); // Wait for save API
    }
    
    // Step 6: Toggle to solution mode
    console.log('[test] [5/9] Toggling to solution mode...');
    const toggleBtn = page.locator('button:has-text("Solution"), button:has-text("Lösung"), [data-testid*="toggle"]')
      .first();
    if (await toggleBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(2000); // Wait for mode switch and Pyodide file sync
    }
    
    // Step 7: Run solution
    console.log('[test] [6/9] Running solution code...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000); // Wait for Pyodide execution
    const solutionRunOutput = await page.locator('#output, [id*="output"], .output').textContent();
    const solutionMarkers = extractTemplateMarkers(solutionRunOutput || '');
    console.log('[test] Solution run - Solution markers:', solutionMarkers.hasSolutionMarkers);
    
    // Step 8: Toggle back to template mode
    console.log('[test] [7/9] Toggling back to template mode...');
    if (await toggleBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(2000); // CRITICAL: Wait for Pyodide to resync template files
    }
    
    // Step 9: Run template again - THIS IS WHERE THE RACE CONDITION MANIFESTS
    console.log('[test] [8/9] Running template code again (critical test)...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000); // Wait for Pyodide execution
    const templateRunOutput2 = await page.locator('#output, [id*="output"], .output').textContent();
    const templateMarkers2 = extractTemplateMarkers(templateRunOutput2 || '');
    console.log('[test] Template run 2 - Template markers:', templateMarkers2.hasTemplateMarkers);
    
    // Step 10: Analyze results
    console.log('[test] [9/9] Analyzing results...');
    
    const passed = 
      templateMarkers1.hasTemplateMarkers &&
      solutionMarkers.hasSolutionMarkers &&
      templateMarkers2.hasTemplateMarkers &&
      !templateMarkers2.hasSolutionMarkers;
    
    const result = {
      passed,
      timestamp: new Date().toISOString(),
      url: TARGET_URL,
      markers: {
        templateRun1: templateMarkers1.hasTemplateMarkers,
        solutionRun: solutionMarkers.hasSolutionMarkers,
        templateRun2: templateMarkers2.hasTemplateMarkers,
        race_condition_detected: !templateMarkers2.hasTemplateMarkers && solutionMarkers.hasSolutionMarkers
      },
      counts: {
        templateRun1: { template: templateMarkers1.templateCount, solution: templateMarkers1.solutionCount },
        solutionRun: { template: solutionMarkers.templateCount, solution: solutionMarkers.solutionCount },
        templateRun2: { template: templateMarkers2.templateCount, solution: templateMarkers2.solutionCount }
      }
    };
    
    // Save results
    if (!fs.existsSync(RESULTS_DIR)) {
      fs.mkdirSync(RESULTS_DIR, { recursive: true });
    }
    
    const filename = `toggle_workflow_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    const filepath = path.join(RESULTS_DIR, filename);
    fs.writeFileSync(filepath, JSON.stringify(result, null, 2));
    
    console.log('\n[test] ═══════════════════════════════════════');
    console.log('[test] TEST RESULT:', passed ? '✓ PASSED' : '✗ FAILED');
    console.log('[test] ═══════════════════════════════════════');
    console.log('[test] Details:');
    console.log('[test]   Template Run 1: ', templateMarkers1.hasTemplateMarkers ? '✓ template markers' : '✗ NO template markers');
    console.log('[test]   Solution Run: ', solutionMarkers.hasSolutionMarkers ? '✓ solution markers' : '✗ NO solution markers');
    console.log('[test]   Template Run 2: ', templateMarkers2.hasTemplateMarkers ? '✓ template markers' : '✗ NO template markers');
    if (result.markers.race_condition_detected) {
      console.log('[test]   ⚠️  RACE CONDITION DETECTED!');
      console.log('[test]      Frontend shows template, but Pyodide executes solution code');
    }
    console.log('[test] Results saved to:', filepath);
    console.log('[test] ═══════════════════════════════════════\n');
    
    process.exit(passed ? 0 : 1);
    
  } catch (error) {
    console.error('[test] Error during test:', error);
    process.exit(2);
  } finally {
    await browser.close();
  }
}

runTest();
