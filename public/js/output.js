export const outputEl = document.getElementById('output');

export function clearOutput() {
    outputEl.textContent = '';
}

export function writeOutput(text) {
    outputEl.textContent += text + '\n';
}
