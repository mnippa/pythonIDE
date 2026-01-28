function createEditor(containerId, callback) {
    require.config({ paths: { vs: 'monaco/min/vs' } });
    require(['vs/editor/editor.main'], function() {
        const editor = monaco.editor.create(
            document.getElementById(containerId),
            {
                value: '# Python Web IDE\nprint("Hello World")',
                language: 'python',
                theme: 'vs-dark',
                automaticLayout: true,
                minimap: { enabled: false }
            }
        );
        callback(editor);
    });
}

// global verfügbar machen
window.createEditor = createEditor;
