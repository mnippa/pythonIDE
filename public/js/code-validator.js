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
   */
  compareOutput(actual, expected, mode = 'loose', testNumber = 1) {
    let actualCleaned = String(actual || '').trim();
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
        html += `Test ${result.testNumber}: ${result.message}<br>`;
        html += `<small>Erwartet: <code>${escapeHtml(result.expected.substring(0, 100))}</code></small><br>`;
        html += `<small>Erhalten: <code>${escapeHtml(result.actual.substring(0, 100))}</code></small>`;
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
