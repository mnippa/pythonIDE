#!/usr/bin/env node
/**
 * playwright_toggle_advanced.cjs
 *
 * ADVANCED TEST: Includes solution code file changes (function.py)
 *
 * Test flow:
 * 1. Load assignment in template mode
 * 2. Run template
 * 3. Toggle to solution mode
 * 4. MODIFY SOLUTION: Edit function.py with different code
 * 5. Run solution (modified)
 * 6. Toggle back to template
 * 7. Run template (should NOT show modified solution code)
 * 8. Toggle to solution again
 * 9. Verify function.py still has the modified code
 *
 * This tests:
 * - File persistence across mode toggles
 * - Modified content handling
 * - Scope isolation (template vs solution files)
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const TARGET_URL = process.env.TARGET_URL ||
  'http://localhost/pythonIDEBeta/public/editor_assignment_test.php?assignment_id=32&task_id=338';

const RESULTS_DIR = process.env.RESULTS_DIR || path.join(__dirname, '..', '..', 'storage', 'test_results');

function extractTemplateMarkers(html) {
  const templatePattern = /🟨/g;
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

async function clickEditorTab(page, tabName) {
  // Try to find and click file tab (init.py, function.py, etc.)
  const tabs = await page.locator('[data-file], [data-path], .file-tab').all();
  for (const tab of tabs) {
    const text = await tab.textContent();
    if (text && text.includes(tabName)) {
      await tab.click();
      await page.waitForTimeout(500);
      return true;
    }
  }
  return false;
}

async function runTest() {
  console.log('[test-advanced] Starting advanced toggle workflow test...');
  console.log('[test-advanced] Target URL:', TARGET_URL);
  
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: false,
    args: ['--disable-blink-features=AutomationControlled']
  });

  try {
    const page = await browser.newPage();
    
    // Step 1: Load assignment
    console.log('[test-advanced] [1/11] Loading assignment editor...');
    await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(2000);
    
    // Step 2: Run template
    console.log('[test-advanced] [2/11] Running template code...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000);
    const templateRun1 = await page.locator('#output, [id*="output"], .output').textContent();
    const templateMarkers1 = extractTemplateMarkers(templateRun1 || '');
    console.log('[test-advanced] Template run 1 - Template markers:', templateMarkers1.hasTemplateMarkers);
    
    // Step 3: Toggle to solution
    console.log('[test-advanced] [3/11] Toggling to solution mode...');
    const toggleBtn = page.locator('button:has-text("Solution"), button:has-text("Lösung")')
      .first();
    if (await toggleBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(2000);
    }
    
    // Step 4: Modify solution code (function.py)
    console.log('[test-advanced] [4/11] Modifying solution code (function.py)...');
    const modified = await clickEditorTab(page, 'function.py');
    if (modified) {
      const editor = page.locator('[class*="editor"], [id*="editor"], .monaco-editor');
      if (await editor.isVisible({ timeout: 2000 }).catch(() => false)) {
        // Try to select all and replace
        await page.keyboard.press('Control+A');
        await page.waitForTimeout(500);
        // Type modified solution code
        await page.keyboard.type('# Modified solution code\nprint("🟩 MODIFIED SOLUTION")');
        await page.waitForTimeout(1000);
        console.log('[test-advanced] Solution code modified');
      }
    } else {
      console.log('[test-advanced] Could not find function.py tab, continuing anyway');
    }
    
    // Step 5: Run solution (modified)
    console.log('[test-advanced] [5/11] Running modified solution...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000);
    const solutionRun = await page.locator('#output, [id*="output"], .output').textContent();
    const solutionMarkers = extractTemplateMarkers(solutionRun || '');
    const hasModifiedCode = (solutionRun || '').includes('MODIFIED SOLUTION');
    console.log('[test-advanced] Solution run - Solution markers:', solutionMarkers.hasSolutionMarkers);
    console.log('[test-advanced] Solution run - Contains modified code:', hasModifiedCode);
    
    // Step 6: Toggle back to template
    console.log('[test-advanced] [6/11] Toggling back to template...');
    if (await toggleBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(2000);
    }
    
    // Step 7: Run template (should NOT show modified code)
    console.log('[test-advanced] [7/11] Running template again...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000);
    const templateRun2 = await page.locator('#output, [id*="output"], .output').textContent();
    const templateMarkers2 = extractTemplateMarkers(templateRun2 || '');
    const templateRun2HasModified = (templateRun2 || '').includes('MODIFIED SOLUTION');
    console.log('[test-advanced] Template run 2 - Template markers:', templateMarkers2.hasTemplateMarkers);
    console.log('[test-advanced] Template run 2 - Contaminated with modified code:', templateRun2HasModified);
    
    // Step 8: Toggle to solution again
    console.log('[test-advanced] [8/11] Toggle to solution again...');
    if (await toggleBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(2000);
    }
    
    // Step 9: Verify function.py still has modified code
    console.log('[test-advanced] [9/11] Verifying solution code persists...');
    const functionPyFound = await clickEditorTab(page, 'function.py');
    let functionPyContent = '';
    if (functionPyFound) {
      const editor = page.locator('[class*="editor"], [id*="editor"], .monaco-editor');
      if (await editor.isVisible({ timeout: 2000 }).catch(() => false)) {
        functionPyContent = await page.evaluate(() => {
          const aceEditor = document.querySelector('[data-editor-type="ace"]');
          if (aceEditor && aceEditor.env && aceEditor.env.editor) {
            return aceEditor.env.editor.getValue();
          }
          // Fallback: try to get from editor state
          const editorState = window.editorInstance?.getValue?.();
          return editorState || '';
        });
      }
    }
    const functionPyModified = functionPyContent.includes('MODIFIED SOLUTION') || functionPyContent.includes('Modified');
    console.log('[test-advanced] Solution code persisted:', functionPyModified);
    
    // Step 10: Run solution again to verify modified code
    console.log('[test-advanced] [10/11] Running solution again to verify...');
    await page.click('text=/Run|▶/', { timeout: 10000 }).catch(() => {
      return page.click('button:has-text("Run")', { timeout: 10000 });
    });
    
    await page.waitForTimeout(3000);
    const solutionRun2 = await page.locator('#output, [id*="output"], .output').textContent();
    const solutionRun2HasModified = (solutionRun2 || '').includes('MODIFIED SOLUTION');
    console.log('[test-advanced] Solution run 2 - Contains modified code:', solutionRun2HasModified);
    
    // Analyze results
    console.log('[test-advanced] [11/11] Analyzing results...');
    
    const result = {
      passed: 
        templateMarkers1.hasTemplateMarkers &&
        solutionMarkers.hasSolutionMarkers &&
        hasModifiedCode &&
        templateMarkers2.hasTemplateMarkers &&
        !templateRun2HasModified &&
        functionPyModified &&
        solutionRun2HasModified,
      timestamp: new Date().toISOString(),
      url: TARGET_URL,
      markers: {
        templateRun1: templateMarkers1.hasTemplateMarkers,
        solutionRunModified: solutionMarkers.hasSolutionMarkers && hasModifiedCode,
        templateRun2: templateMarkers2.hasTemplateMarkers,
        templateRun2Contaminated: templateRun2HasModified,
        functionPyPersisted: functionPyModified,
        solutionRun2Modified: solutionRun2HasModified
      },
      testCases: {
        templateMarkers1: { expected: true, actual: templateMarkers1.hasTemplateMarkers, pass: templateMarkers1.hasTemplateMarkers },
        solutionCodeModified: { expected: true, actual: hasModifiedCode, pass: hasModifiedCode },
        solutionMarkers: { expected: true, actual: solutionMarkers.hasSolutionMarkers, pass: solutionMarkers.hasSolutionMarkers },
        templateMarkers2: { expected: true, actual: templateMarkers2.hasTemplateMarkers, pass: templateMarkers2.hasTemplateMarkers },
        templateNotContaminated: { expected: true, actual: !templateRun2HasModified, pass: !templateRun2HasModified },
        functionPyPersists: { expected: true, actual: functionPyModified, pass: functionPyModified },
        solutionRun2Modified: { expected: true, actual: solutionRun2HasModified, pass: solutionRun2HasModified }
      }
    };
    
    // Save results
    if (!fs.existsSync(RESULTS_DIR)) {
      fs.mkdirSync(RESULTS_DIR, { recursive: true });
    }
    
    const filename = `toggle_advanced_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    const filepath = path.join(RESULTS_DIR, filename);
    fs.writeFileSync(filepath, JSON.stringify(result, null, 2));
    
    console.log('\n[test-advanced] ═══════════════════════════════════════');
    console.log('[test-advanced] TEST RESULT:', result.passed ? '✓ PASSED' : '✗ FAILED');
    console.log('[test-advanced] ═══════════════════════════════════════');
    console.log('[test-advanced] Test Results:');
    Object.entries(result.testCases).forEach(([name, tc]) => {
      const status = tc.pass ? '✓' : '✗';
      console.log(`[test-advanced]   ${status} ${name}: ${tc.actual} (expected ${tc.expected})`);
    });
    console.log('[test-advanced] Results saved to:', filepath);
    console.log('[test-advanced] ═══════════════════════════════════════\n');
    
    process.exit(result.passed ? 0 : 1);
    
  } catch (error) {
    console.error('[test-advanced] Error during test:', error);
    process.exit(2);
  } finally {
    await browser.close();
  }
}

runTest();
