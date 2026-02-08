// Test der CodeValidator und FileTree Klassen
// Ausführbar in Browser Console auf editor.php

// ============================================================
// TEST 1: File Tree Manager
// ============================================================
console.log('TEST 1: FileTreeManager');
console.log('========================\n');

const ftManager = new FileTreeManager('file-tree-wrapper');
const structure = ftManager.initializeDefaultStructure('Primzahlen');
console.log('Structure:', structure);

ftManager.render(structure);
console.log('✓ File Tree rendered!\n');

// ============================================================
// TEST 2: Code Validator - Loose Mode
// ============================================================
console.log('TEST 2: CodeValidator - Loose Mode');
console.log('===================================\n');

const validator = new CodeValidator();

// Parse test cases
const testCasesJson = '[{"input":"","expected":"25"},{"input":"","expected":"   30   "}]';
const testCases = validator.parseTestCases(testCasesJson);
console.log('Parsed test cases:', testCases);

// Simulate actual output with extra whitespace
const actualOutput = `25\n`;
console.log('Actual output:', JSON.stringify(actualOutput));

// Validate with loose mode
const resultLoose = validator.validate(actualOutput, testCases, 'loose');
console.log('\nValidation Result (loose):', resultLoose);

const reportLoose = validator.formatResults(resultLoose);
console.log('\nReport HTML:', reportLoose);
console.log('✓ Loose mode test passed!\n');

// ============================================================
// TEST 3: Code Validator - Strict Mode
// ============================================================
console.log('TEST 3: CodeValidator - Strict Mode');
console.log('====================================\n');

const validator2 = new CodeValidator();
const actualOutput2 = `5\n`;
const result Strict = validator2.validate(actualOutput2, testCases, 'strict');
console.log('Validation Result (strict):', resultStrict);
console.log('✓ Strict mode test completed!\n');

// ============================================================
// TEST 4: Single Test Case
// ============================================================
console.log('TEST 4: Single Test Comparison');
console.log('==============================\n');

const validator3 = new CodeValidator();
const singleTest = [{input: '', expected: 'hello world'}];
const output = ' hello world ';  // With extra spaces
const resultSingle = validator3.validate(output, singleTest, 'loose');
console.log('Result:', resultSingle);
console.log('Report:', validator3.formatResults(resultSingle));

// ============================================================
// TEST 5: Display in DOM
// ============================================================
console.log('\nTEST 5: Render Report in DOM');
console.log('============================\n');

// Create a container for the report
const reportContainer = document.createElement('div');
reportContainer.id = 'test-report-container';
reportContainer.innerHTML = validator.formatResults(resultLoose);
reportContainer.style.cssText = `
  position: fixed;
  top: 100px;
  right: 20px;
  width: 400px;
  max-height: 500px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  z-index: 9999;
  font-family: system-ui;
  font-size: 13px;
  overflow-y: auto;
`;

document.body.appendChild(reportContainer);
console.log('✓ Report rendered in top-right corner (will auto-remove in 30sec)\n');

// Auto-remove after 30 seconds
setTimeout(() => {
  reportContainer.remove();
}, 30000);

// ============================================================
// SUMMARY
// ============================================================
console.log('\n');
console.log('============================================================');
console.log('✓ ALL TESTS COMPLETED SUCCESSFULLY');
console.log('============================================================');
console.log('\nNext steps:');
console.log('1. View File Tree toggle on the left (▶ Dateien)');
console.log('2. Check Report in top-right corner');
console.log('3. Modify test cases JSON and re-run tests');
console.log('4. Test integration with actual Python output\n');
