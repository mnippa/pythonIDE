#!/usr/bin/env node
/**
 * playwright_toggle_workflow_fixed.cjs
 *
 * Tests the exact user workflow that previously failed with race conditions.
 * FIXED: Uses ID-based selectors instead of text matching
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

async function runTest() {
  console.log('[test-fixed] Starting basic toggle workflow test...');
  console.log('[test-fixed] Target URL:', TARGET_URL);
  
  const browser = await chromium.launch({
    channel: 'msedge',
    headless: false,
    args: ['--disable-blink-features=AutomationControlled']
  });

  try {
    const page = await browser.newPage();
    
    // Step 1: Navigate to assignment
    console.log('[test-fixed] [1/9] Loading assignment editor...');
    await page.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: 30000 });
    await page.waitForTimeout(3000); // Wait for full initialization
    
    // Step 2: Run template mode using ID selector
    console.log('[test-fixed] [2/9] Running template code...');
    try {
      const runBtn = await page.$('#run-btn');
      if (!runBtn) {
        throw new Error('Run button not found with ID #run-btn');
      }
      await runBtn.click();
      await page.waitForTimeout(4000);
    } catch (err) {
      console.error('[test-fixed] Could not click run button:', err.message);
      throw err;
    }
    
    const templateRunOutput = await page.evaluate(() => {
      const el = document.getElementById('output-container') || 
                 document.querySelector('[id*="output"]') ||
                 document.querySelector('.output');
      return el ? el.textContent : '';
    });
    const templateMarkers1 = extractTemplateMarkers(templateRunOutput || '');
    console.log('[test-fixed] Template run 1 - Template markers:', templateMarkers1.hasTemplateMarkers);
    
    // Step 3: Save (simulated by just waiting)
    console.log('[test-fixed] [3/9] Waiting...');
    await page.waitForTimeout(1000);
    
    // Step 4: Toggle to solution mode
    console.log('[test-fixed] [4/9] Toggling to solution mode...');
    const toggleBtn = await page.$('#solution-toggle-btn, button[id*="solution"], button[id*="toggle"]');
    if (toggleBtn) {
      await toggleBtn.click();
      await page.waitForTimeout(2500);
    } else {
      console.log('[test-fixed] Solution toggle button not found, trying text search...');
      const allButtons = await page.$$('button');
      for (const btn of allButtons) {
        const text = await btn.textContent();
        if (text && (text.includes('Lösung') || text.includes('Solution'))) {
          await btn.click();
          await page.waitForTimeout(2500);
          break;
        }
      }
    }
    
    // Step 5: Run solution
    console.log('[test-fixed] [5/9] Running solution code...');
    const runBtn2 = await page.$('#run-btn');
    if (runBtn2) {
      await runBtn2.click();
      await page.waitForTimeout(4000);
    }
    
    const solutionRunOutput = await page.evaluate(() => {
      const el = document.getElementById('output-container') || 
                 document.querySelector('[id*="output"]') ||
                 document.querySelector('.output');
      return el ? el.textContent : '';
    });
    const solutionMarkers = extractTemplateMarkers(solutionRunOutput || '');
    console.log('[test-fixed] Solution run - Solution markers:', solutionMarkers.hasSolutionMarkers);
    
    // Step 6: Toggle back to template mode
    console.log('[test-fixed] [6/9] Toggling back to template mode...');
    const toggleBtn2 = await page.$('#solution-toggle-btn, button[id*="solution"], button[id*="toggle"]');
    if (toggleBtn2) {
      await toggleBtn2.click();
      await page.waitForTimeout(2500);
    } else {
      const allButtons = await page.$$('button');
      for (const btn of allButtons) {
        const text = await btn.textContent();
        if (text && (text.includes('Lösung') || text.includes('Solution') || text.includes('Template'))) {
          await btn.click();
          await page.waitForTimeout(2500);
          break;
        }
      }
    }
    
    // Step 7: Run template again - CRITICAL TEST
    console.log('[test-fixed] [7/9] Running template again (critical test)...');
    const runBtn3 = await page.$('#run-btn');
    if (runBtn3) {
      await runBtn3.click();
      await page.waitForTimeout(4000);
    }
    
    const templateRunOutput2 = await page.evaluate(() => {
      const el = document.getElementById('output-container') || 
                 document.querySelector('[id*="output"]') ||
                 document.querySelector('.output');
      return el ? el.textContent : '';
    });
    const templateMarkers2 = extractTemplateMarkers(templateRunOutput2 || '');
    console.log('[test-fixed] Template run 2 - Template markers:', templateMarkers2.hasTemplateMarkers);
    
    // Analyze results
    console.log('[test-fixed] [8/9] Analyzing results...');
    
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
    
    const filename = `toggle_basic_${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
    const filepath = path.join(RESULTS_DIR, filename);
    fs.writeFileSync(filepath, JSON.stringify(result, null, 2));
    
    console.log('\n[test-fixed] ═══════════════════════════════════════');
    console.log('[test-fixed] TEST RESULT:', passed ? '✓ PASSED' : '✗ FAILED');
    console.log('[test-fixed] ═══════════════════════════════════════');
    console.log('[test-fixed] Details:');
    console.log('[test-fixed]   Template Run 1: ', templateMarkers1.hasTemplateMarkers ? '✓ template markers' : '✗ NO template markers');
    console.log('[test-fixed]   Solution Run: ', solutionMarkers.hasSolutionMarkers ? '✓ solution markers' : '✗ NO solution markers');
    console.log('[test-fixed]   Template Run 2: ', templateMarkers2.hasTemplateMarkers ? '✓ template markers' : '✗ NO template markers');
    if (result.markers.race_condition_detected) {
      console.log('[test-fixed]   ⚠️  RACE CONDITION DETECTED!');
      console.log('[test-fixed]      Frontend shows template, but Pyodide executes solution code');
    }
    console.log('[test-fixed] Results saved to:', filepath);
    console.log('[test-fixed] ═══════════════════════════════════════\n');
    
    process.exit(passed ? 0 : 1);
    
  } catch (error) {
    console.error('[test-fixed] Error during test:', error);
    process.exit(2);
  } finally {
    await browser.close();
  }
}

runTest();
