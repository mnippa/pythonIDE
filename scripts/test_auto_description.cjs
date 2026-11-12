const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const sourcePath = process.argv[2] || path.join(__dirname, '..', 'public', 'js', 'admin-dashboard.js');
const source = fs.readFileSync(sourcePath, 'utf8');
const start = source.indexOf('function generateAutoDescription(');
const end = source.indexOf('\n// Initialize on page load', start);
assert.ok(start !== -1 && end !== -1, 'Auto-Desc function not found');

for (const edit of [false, true]) {
  for (const useEditor of [false, true]) {
    const id = edit ? 'edit-task-description' : 'task-description';
    const field = { value: '' };
    let editorContent = '';
    const context = {
      document: { getElementById: name => name === id ? field : { checked: false } },
      tinymce: { get: () => useEditor ? {
        getContent: () => editorContent,
        setContent: value => { editorContent = value; }
      } : null },
      escapeHtml: value => String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'),
      alert: () => {}
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end), context);
    function generate(cases) {
      field.value = '';
      editorContent = '';
      context.generateAutoDescription(cases, id);
      return useEditor ? editorContent : field.value;
    }

    let html = generate([{type: 'code_check', operator: 'NOT', keywords: ['max(', 'sum(']}]);
    assert.match(html, /Verbotene Keywords<\/td><td>max\(, sum\(/);
    assert.doesNotMatch(html, /Erforderliche Keywords/);

    html = generate([{type: 'code_check', operator: 'AND', keywords: ['for', 'if']}]);
    assert.match(html, /Erforderliche Keywords<\/td><td>for, if/);
    assert.doesNotMatch(html, /Verbotene Keywords/);

    html = generate([
      {type: 'code_check', operator: 'NOT', keywords: ['max(']},
      {type: 'code_check', operator: 'AND', keywords: ['for', 'for']},
      {type: 'code_check', operator: 'OR', keywords: ['if', 'while']},
      {type: 'code_check', operator: 'NOT', keywords: ['sum(', 'max(']}
    ]);
    assert.match(html, /Erforderliche Keywords<\/td><td>for; mindestens eines: if, while/);
    assert.match(html, /Verbotene Keywords<\/td><td>max\(, sum\(/);
    assert.equal((html.match(/Erforderliche Keywords/g) || []).length, 1);
    assert.equal((html.match(/Verbotene Keywords/g) || []).length, 1);

    html = generate([{type: 'code_check', operator: 'NOT', keywords: []}]);
    assert.doesNotMatch(html, /Erforderliche Keywords|Verbotene Keywords/);

    html = generate([{type: 'code_check', keywords: ['<tag>']}]);
    assert.match(html, /&lt;tag&gt;/);

    html = generate([
      {type: 'code_check', operator: 'NOT', keywords: ['math', '\\b(?:max|sum)\\s*\\(']},
      {type: 'code_check', operator: 'AND', keywords: ['\\bfor\\b', '\\bif\\b', '\\bprint\\s*\\(']}
    ]);
    assert.match(html, /Erforderliche Keywords<\/td><td>for, if, print\(\)/);
    assert.match(html, /Verbotene Keywords<\/td><td>math, max\(\), sum\(\)/);
    assert.doesNotMatch(html, /\\b|\\s/);

    html = generate([{type: 'code_check', operator: 'AND', keywords: ['\\b(?:max|sum)\\s*\\(']}]);
    assert.match(html, /mindestens eines: max\(\), sum\(\)/);
    html = generate([{type: 'code_check', operator: 'OR', keywords: ['\\b(?:max|sum)\\s*\\(', '\\bprint\\b']}]);
    assert.match(html, /mindestens eines: max\(\), sum\(\), print/);
    html = generate([{type: 'code_check', operator: 'NOT', keywords: ['x\\d+']}]);
    assert.match(html, /x\\d\+/);
  }
}
console.log(`Auto-Desc tests passed: ${sourcePath}`);
