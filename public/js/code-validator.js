// code-validator.js - Validate Python code output against test cases

class CodeValidator {
  constructor() {
    this.lastOutput = '';
    this.testResults = [];
  }

  /**
   * Parse test cases from JSON string
   * Format: [{"input": "", "expected": "expected output"}, ...]
   */
  parseTestCases(testCasesJson) {
    try {
      if (!testCasesJson) return [];
      const cases = JSON.parse(testCasesJson);
      return Array.isArray(cases) ? cases : [];
    } catch (e) {
      console.error('Failed to parse test cases:', e);
      return [];
    }
  }

  /**
   * Run validation against test cases
   */
  validate(actualOutput, testCases, validationMode = 'loose') {
    this.lastOutput = actualOutput;
    this.testResults = [];

    if (!testCases || testCases.length === 0) {
      return {
        passed: true,
        total: 0,
        message: 'Keine Testfälle definiert',
        results: []
      };
    }

    testCases.forEach((testCase, idx) => {
      const result = this.compareOutput(
        actualOutput,
        testCase.expected || '',
        validationMode,
        idx + 1
      );
      this.testResults.push(result);
    });

    const passed = this.testResults.every(r => r.passed);
    const total = this.testResults.length;
    const passedCount = this.testResults.filter(r => r.passed).length;

    return {
      passed,
      total,
      passedCount,
      message: `${passedCount}/${total} Tests bestanden`,
      results: this.testResults
    };
  }

  /**
   * Compare actual vs expected output
   * Expected can be:
   * - String: exact match required
   * - Array: match ANY of the values (OR logic)
   */
  compareOutput(actual, expected, mode = 'loose', testNumber = 1) {
    let actualCleaned = String(actual || '').trim();

    // Handle array of expected values (OR logic)
    if (Array.isArray(expected)) {
      const results = expected.map(exp => {
        let expectedCleaned = String(exp || '').trim();
        
        if (mode === 'loose') {
          const actualLoose = actualCleaned.replace(/\s+/g, ' ');
          const expectedLoose = expectedCleaned.replace(/\s+/g, ' ');
          return actualLoose === expectedLoose;
        }
        
        return actualCleaned === expectedCleaned;
      });

      const passed = results.some(r => r); // Pass if ANY matches
      const matchedValue = expected[results.indexOf(true)];

      return {
        testNumber,
        passed,
        expected: expected.length > 1 
          ? `Eine von ${expected.length} möglichen Lösungen` 
          : expected[0],
        expectedOptions: expected,
        actual: actual,
        matchedOption: matchedValue,
        mode,
        message: passed 
          ? `✓ Test ${testNumber} bestanden${matchedValue ? ` (Lösung: "${matchedValue}")` : ''}`
          : `✗ Test ${testNumber} fehlgeschlagen (Keine der ${expected.length} Lösungen passt)`
      };
    }

    // Single expected value
    let expectedCleaned = String(expected || '').trim();

    if (mode === 'loose') {
      // Ignore extra whitespace differences
      actualCleaned = actualCleaned.replace(/\s+/g, ' ');
      expectedCleaned = expectedCleaned.replace(/\s+/g, ' ');
    }

    const passed = actualCleaned === expectedCleaned;

    return {
      testNumber,
      passed,
      expected: expected,
      actual: actual,
      mode,
      message: passed 
        ? `✓ Test ${testNumber} bestanden`
        : `✗ Test ${testNumber} fehlgeschlagen`
    };
  }

  /**
   * Format validation results for display
   */
  formatResults(validationResult) {
    let html = '<div class="validation-report">';
    
    if (validationResult.total === 0) {
      html += '<p class="validation-info">Keine Testfälle vorhanden</p>';
    } else {
      const statusClass = validationResult.passed ? 'validation-success' : 'validation-failure';
      html += `<div class="validation-status ${statusClass}">`;
      html += `<strong>${validationResult.message}</strong>`;
      html += '</div>';

      html += '<div class="validation-details">';
      validationResult.results.forEach(result => {
        const resultClass = result.passed ? 'test-pass' : 'test-fail';
        html += `<div class="test-result ${resultClass}">`;
        html += `<span class="test-icon">${result.passed ? '✓' : '✗'}</span>`;
        html += `<span class="test-info">`;
        html += `Test ${result.testNumber}: ${result.passed ? 'Bestanden' : 'Fehlgeschlagen'}`;
        
        // Show which option matched if multiple were available
        if (result.passed && result.matchedOption && result.expectedOptions && result.expectedOptions.length > 1) {
          html += ` <span style="color:#10b981;font-size:11px;">(${result.matchedOption})</span>`;
        }
        
        // Show available options for failed tests
        if (!result.passed && result.expectedOptions && result.expectedOptions.length > 1) {
          html += `<br><span style="font-size:11px;color:var(--text-secondary);">Akzeptierte Lösungen: ${result.expectedOptions.length}</span>`;
        }
        
        html += `</span>`;
        html += `</div>`;
      });
      html += '</div>';
    }

    html += '</div>';
    return html;
  }
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Export
window.CodeValidator = CodeValidator;
