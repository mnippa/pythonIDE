// ide.js
import * as monaco from '../monaco/bin/monaco.js';

// init Monaco Editor
export function createEditor(containerId) {
  const container = document.getElementById(containerId);
  return monaco.editor.create(container, {
    value: '# Schreibe hier Python-Code',
    language: 'python',
    theme: 'vs-dark',
    automaticLayout: true,
  });
}
