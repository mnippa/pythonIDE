export function createInternalClipboard() {
  const state = { clipboard: null, notify: null, findText: '', resources: [] };
  return {
    services: {
      clipboardService: {
        async writeText(text) {
          state.clipboard = { texts: [text], wholeLines: false, separator: '\n' };
        },
        async readText() {
          if (!state.clipboard) {
            state.notify?.('Nur im Codefenster kopierter Text kann eingefügt werden.');
            return '';
          }
          return state.clipboard.texts.join(state.clipboard.wholeLines ? '' : state.clipboard.separator);
        },
        async writeFindText(text) { state.findText = text; },
        async readFindText() { return state.findText; },
        async writeResources(resources) { state.resources = resources; },
        async readResources() { return state.resources; },
        async hasResources() { return state.resources.length > 0; }
      }
    },
    install(editor, monaco) {
      installInternalClipboard(editor, monaco, state);
    }
  };
}

function installInternalClipboard(editor, monaco, state) {
  const host = editor.getContainerDomNode();
  editor.updateOptions({ dragAndDrop: false });
  const listeners = [];
  const listen = (target, type, handler) => {
    target.addEventListener(type, handler, true);
    listeners.push(() => target.removeEventListener(type, handler, true));
  };
  const stop = event => {
    event.preventDefault();
    event.stopImmediatePropagation();
  };
  const editable = () => !editor.getOption(monaco.editor.EditorOption.readOnly);
  const selections = () => editor.getSelections() || [];
  const hasSelection = () => selections().some(selection => !selection.isEmpty());

  const notice = document.createElement('div');
  notice.setAttribute('role', 'status');
  notice.style.cssText = 'position:absolute;bottom:8px;right:8px;max-width:90%;padding:8px;border-radius:4px;background:var(--panel,#fff);color:var(--text-primary,#222);border:1px solid var(--border,#ccc);z-index:100;display:none;font:13px system-ui;';
  host.appendChild(notice);
  let noticeTimer;
  const notify = text => {
    notice.textContent = text;
    notice.style.display = 'block';
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.style.display = 'none'; }, 3500);
  };
  state.notify = notify;

  const edit = (edits, atEnd) => {
    editor.pushUndoStop();
    editor.executeEdits('internal-clipboard', edits, inverseEdits => inverseEdits.map(edit => {
      const position = atEnd ? edit.range.getEndPosition() : edit.range.getStartPosition();
      return new monaco.Selection(position.lineNumber, position.column, position.lineNumber, position.column);
    }));
    editor.pushUndoStop();
  };
  const copy = cut => {
    const model = editor.getModel();
    const ranges = selections();
    if (!model || !ranges.length || (cut && !editable())) return;
    const wholeLines = ranges.every(range => range.isEmpty());
    const parts = wholeLines
      ? [...new Set(ranges.map(range => range.startLineNumber))].map(line => ({
        line, text: model.getLineContent(line) + model.getEOL()
      }))
      : ranges.filter(range => !range.isEmpty()).map(range => ({
        range, text: model.getValueInRange(range)
      }));
    state.clipboard = { texts: parts.map(part => part.text), wholeLines, separator: model.getEOL() };
    if (cut) {
      const edits = parts.map(part => {
        let range = part.range;
        if (wholeLines) {
          const line = part.line;
          range = line < model.getLineCount()
            ? new monaco.Range(line, 1, line + 1, 1)
            : line > 1
              ? new monaco.Range(line - 1, model.getLineMaxColumn(line - 1), line, model.getLineMaxColumn(line))
              : new monaco.Range(1, 1, 1, model.getLineMaxColumn(1));
        }
        return { range, text: '', forceMoveMarkers: true };
      });
      const merged = [];
      for (const entry of edits.sort((a, b) => monaco.Range.compareRangesUsingStarts(a.range, b.range))) {
        const previous = merged[merged.length - 1];
        if (previous && monaco.Range.areIntersectingOrTouching(previous.range, entry.range)) {
          previous.range = monaco.Range.plusRange(previous.range, entry.range);
        } else {
          merged.push(entry);
        }
      }
      edit(merged, false);
    }
  };
  const paste = () => {
    const model = editor.getModel();
    if (!model || !editable()) return;
    if (!state.clipboard) {
      notify('Nur im Codefenster kopierter Text kann eingefügt werden.');
      return;
    }
    const ranges = selections();
    const texts = state.clipboard.texts;
    const text = texts.join(state.clipboard.wholeLines ? '' : model.getEOL());
    edit(ranges.map((selection, index) => ({
      range: state.clipboard.wholeLines && selection.isEmpty()
        ? new monaco.Range(selection.startLineNumber, 1, selection.startLineNumber, 1)
        : selection,
      text: texts.length === ranges.length ? texts[index] : text,
      forceMoveMarkers: true
    })), true);
  };

  const menu = document.createElement('div');
  menu.setAttribute('role', 'menu');
  menu.setAttribute('aria-label', 'Interne Zwischenablage');
  menu.style.cssText = 'position:fixed;display:none;padding:4px;z-index:10000;background:var(--panel,#fff);color:var(--text-primary,#222);border:1px solid var(--border,#ccc);border-radius:5px;box-shadow:0 3px 12px #0003;font:13px system-ui;';
  const closeMenu = () => { menu.style.display = 'none'; };
  const actions = [
    { label: 'Kopieren (intern)', run: () => copy(false), enabled: () => !!editor.getModel() },
    { label: 'Ausschneiden (intern)', run: () => copy(true), enabled: () => !!editor.getModel() && editable() },
    { label: 'Einfügen (intern)', run: paste, enabled: () => !!state.clipboard && editable() }
  ];
  const buttons = actions.map(action => {
    const button = document.createElement('button');
    button.type = 'button';
    button.setAttribute('role', 'menuitem');
    button.textContent = action.label;
    button.style.cssText = 'display:block;width:100%;text-align:left;padding:7px 12px;border:0;background:transparent;color:inherit;font:inherit;cursor:pointer;';
    button.addEventListener('click', () => {
      closeMenu();
      editor.focus();
      action.run();
    });
    menu.appendChild(button);
    return button;
  });
  document.body.appendChild(menu);
  const openMenu = (x, y) => {
    buttons.forEach((button, index) => {
      button.disabled = !actions[index].enabled();
      button.style.opacity = button.disabled ? '0.45' : '1';
    });
    menu.style.display = 'block';
    menu.style.left = `${Math.max(0, Math.min(x, window.innerWidth - menu.offsetWidth))}px`;
    menu.style.top = `${Math.max(0, Math.min(y, window.innerHeight - menu.offsetHeight))}px`;
    buttons.find(button => !button.disabled)?.focus();
  };
  listen(host, 'contextmenu', event => {
    stop(event);
    const target = editor.getTargetAtClientPoint(event.clientX, event.clientY);
    if (!hasSelection() && target?.position) editor.setPosition(target.position);
    openMenu(event.clientX, event.clientY);
  });
  listen(host, 'keydown', event => {
    const key = event.key.toLowerCase();
    const modifier = (event.ctrlKey || event.metaKey) && !event.altKey;
    if ((modifier && ['c', 'x', 'v', 'insert'].includes(key))
      || (event.shiftKey && ['insert', 'delete'].includes(key))) {
      stop(event);
      if (key === 'c' || (key === 'insert' && !event.shiftKey)) copy(false);
      else if (key === 'x' || key === 'delete') copy(true);
      else paste();
    } else if (key === 'contextmenu' || (event.shiftKey && key === 'f10')) {
      stop(event);
      const bounds = host.getBoundingClientRect();
      openMenu(bounds.left + 20, bounds.top + 20);
    }
  });
  // Capture before Monaco's document-level clipboard handlers, but only inside this editor.
  for (const [type, action] of [['copy', () => copy(false)], ['cut', () => copy(true)], ['paste', paste]]) {
    listen(window, type, event => {
      if (!host.contains(event.target)) return;
      stop(event);
      action();
    });
  }
  listen(host, 'beforeinput', event => {
    if (event.inputType?.startsWith('insertFromPaste')
      || ['insertFromDrop', 'deleteByCut'].includes(event.inputType)) stop(event);
  });
  for (const type of ['dragstart', 'dragover', 'drop']) {
    listen(host, type, event => {
      stop(event);
      if (type === 'drop') notify('Bitte Code intern kopieren oder ausschneiden und einfügen.');
    });
  }
  listen(document, 'pointerdown', event => {
    if (!menu.contains(event.target)) closeMenu();
  });
  listen(window, 'blur', event => {
    if (event.target === window) closeMenu();
  });
  listen(menu, 'keydown', event => {
    if (event.key === 'Escape' || event.key === 'Tab') {
      stop(event);
      closeMenu();
      editor.focus();
    } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      stop(event);
      const enabled = buttons.filter(button => !button.disabled);
      const index = enabled.indexOf(document.activeElement);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1
        : (index + (event.key === 'ArrowDown' ? 1 : -1) + enabled.length) % enabled.length;
      enabled[next]?.focus();
    }
  });
  const disposable = editor.onDidDispose(() => {
    listeners.forEach(remove => remove());
    clearTimeout(noticeTimer);
    state.clipboard = null;
    state.notify = null;
    state.findText = '';
    state.resources = [];
    menu.remove();
    notice.remove();
    disposable.dispose();
  });
}
