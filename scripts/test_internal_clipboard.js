const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public', 'js', 'editor-internal-clipboard.js'), 'utf8');
assert.doesNotMatch(source, /navigator\.clipboard|clipboardData|execCommand/);
const setup = fs.readFileSync(path.join(root, 'public', 'js', 'editor-setup.js'), 'utf8');
assert.match(setup, /!isProjectsPage && !isTestMode \? createInternalClipboard\(\) : null/);
assert.match(setup, /internalClipboard\?\.services/);
assert.doesNotMatch(setup, /editor\.onDidPaste/);

async function verifyInternalClipboard() {
  const assert = (condition, message) => { if (!condition) throw new Error(message); };
  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;left:20px;top:20px;width:600px;height:300px;z-index:20000;';
  document.body.appendChild(host);
  const clipboard = createInternalClipboard();
  const editor = monaco.editor.create(host, {
    value: 'alpha\nbeta\ngamma', language: 'python', contextmenu: false
  }, clipboard.services);
  const menusBefore = document.querySelectorAll('[aria-label="Interne Zwischenablage"]').length;
  const key = (key, options = {}) => {
    const event = new KeyboardEvent('keydown', { key, ctrlKey: true, bubbles: true, cancelable: true, ...options });
    editor.getDomNode().dispatchEvent(event);
    assert(event.defaultPrevented, `Shortcut not intercepted: ${key}`);
  };
  const value = expected => assert(editor.getValue() === expected, `Expected ${JSON.stringify(expected)}, got ${JSON.stringify(editor.getValue())}`);
  const select = (a, b, c, d) => editor.setSelection(new monaco.Selection(a, b, c, d));
  const native = type => {
    const data = new DataTransfer();
    data.setData('text/plain', 'EXTERNAL CODE');
    const event = type === 'paste'
      ? new ClipboardEvent(type, { clipboardData: data, bubbles: true, cancelable: true })
      : new Event(type, { bubbles: true, cancelable: true });
    editor.getDomNode().dispatchEvent(event);
    assert(event.defaultPrevented, `${type} not blocked`);
  };
  try {
    clipboard.install(editor, monaco);
    editor.focus();
    key('v');
    value('alpha\nbeta\ngamma');
    native('paste');
    value('alpha\nbeta\ngamma');
    select(1, 1, 1, 6);
    key('c');
    editor.setPosition({ lineNumber: 2, column: 5 });
    key('v');
    value('alpha\nbetaalpha\ngamma');
    await editor.getModel().undo();
    value('alpha\nbeta\ngamma');
    await editor.getModel().redo();
    value('alpha\nbetaalpha\ngamma');
    select(1, 1, 1, 6);
    key('x');
    value('\nbetaalpha\ngamma');
    await editor.getModel().undo();
    value('alpha\nbetaalpha\ngamma');

    editor.setValue('one\ntwo\nthree');
    select(1, 1, 1, 4);
    key('Insert');
    editor.setPosition({ lineNumber: 2, column: 4 });
    window.dispatchEvent(new Event('blur'));
    key('Insert', { ctrlKey: false, shiftKey: true });
    value('one\ntwoone\nthree');
    select(1, 1, 1, 4);
    key('c', { ctrlKey: false, metaKey: true });
    select(3, 1, 3, 6);
    key('v', { ctrlKey: false, metaKey: true });
    value('one\ntwoone\none');
    select(1, 1, 1, 4);
    key('Delete', { ctrlKey: false, shiftKey: true });
    value('\ntwoone\none');

    editor.setValue('one\ntwo\nthree');
    editor.setPosition({ lineNumber: 1, column: 2 });
    key('c');
    editor.setPosition({ lineNumber: 2, column: 2 });
    key('v');
    value('one\none\ntwo\nthree');
    editor.setPosition({ lineNumber: 4, column: 1 });
    key('x');
    value('one\none\ntwo');
    await editor.getModel().undo();
    value('one\none\ntwo\nthree');
    editor.setSelections([new monaco.Selection(3, 1, 3, 1), new monaco.Selection(4, 1, 4, 1)]);
    key('x');
    value('one\none\n');
    await editor.getModel().undo();
    value('one\none\ntwo\nthree');

    editor.setValue('abc def\n123 456');
    editor.setSelections([new monaco.Selection(1, 1, 1, 4), new monaco.Selection(2, 1, 2, 4)]);
    native('copy');
    assert(await clipboard.services.clipboardService.readText() === 'abc\n123', 'Multiple-selection copy lost');
    editor.setSelections([new monaco.Selection(1, 5, 1, 8), new monaco.Selection(2, 5, 2, 8)]);
    native('paste');
    value('abc abc\n123 123');
    native('drop');
    value('abc abc\n123 123');
    const beforeInput = new InputEvent('beforeinput', { inputType: 'insertFromPaste', data: 'EXTERNAL', bubbles: true, cancelable: true });
    editor.getDomNode().dispatchEvent(beforeInput);
    assert(beforeInput.defaultPrevented, 'beforeinput paste not blocked');
    editor.updateOptions({ readOnly: true });
    key('v');
    key('x');
    value('abc abc\n123 123');
    editor.updateOptions({ readOnly: false });

    select(1, 1, 1, 4);
    editor.getDomNode().dispatchEvent(new MouseEvent('contextmenu', { clientX: 100, clientY: 100, bubbles: true, cancelable: true }));
    let menu = document.querySelector('[aria-label="Interne Zwischenablage"]');
    assert(menu.style.display === 'block', 'Menu not opened');
    menu.querySelectorAll('button')[0].click();
    select(2, 1, 2, 4);
    key('F10', { ctrlKey: false, shiftKey: true });
    menu.querySelectorAll('button')[2].click();
    value('abc abc\nabc 123');
    await editor.getModel().undo();
    value('abc abc\n123 123');
    await clipboard.services.clipboardService.writeText('abc');
    assert(await clipboard.services.clipboardService.readText() === 'abc', 'Monaco clipboard service not internal');
    select(2, 1, 2, 4);
    key('v');
    value('abc abc\nabc 123');
    await editor.getModel().undo();
    value('abc abc\n123 123');
    await editor.getModel().redo();
    value('abc abc\nabc 123');
    const outside = document.createElement('textarea');
    document.body.appendChild(outside);
    outside.focus();
    const outsidePaste = new ClipboardEvent('paste', { bubbles: true, cancelable: true });
    outside.dispatchEvent(outsidePaste);
    outside.remove();
    assert(!outsidePaste.defaultPrevented, 'Clipboard outside editor was blocked');
    return { status: 'PASS', keyboard: true, nativeEvents: true, lineCopyCut: true, multiSelection: true, undoRedo: true, menu: true, externalPasteBlocked: true, readOnly: true };
  } finally {
    const model = editor.getModel();
    editor.dispose();
    assert(await clipboard.services.clipboardService.readText() === '', 'Buffer retained after editor disposal');
    model.dispose();
    host.remove();
    assert(document.querySelectorAll('[aria-label="Interne Zwischenablage"]').length === menusBefore, 'Menu leaked after disposal');
  }
}

const index = process.argv.indexOf('--browser-bundle');
if (index >= 0) {
  const target = process.argv[index + 1];
  assert.ok(target, 'Missing bundle path');
  const runtime = process.argv.includes('--tests-only') ? '' : source.replace('export function ', 'function ');
  fs.writeFileSync(target, `${runtime}\nwindow.verifyInternalClipboard = ${verifyInternalClipboard.toString()};\n`);
}
console.log('PASS: restricted-mode wiring and no system clipboard access');
