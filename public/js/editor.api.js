export function createEditor(containerId, initialCode = '') {
    return new Promise((resolve) => {
        require(['vs/editor/editor.main'], function () {
            const editor = monaco.editor.create(document.getElementById(containerId), {
                value: initialCode,
                language: 'python',
                automaticLayout: true,
                theme: 'vs-light',
                minimap: { enabled: false },
                fontSize: 14,
            });
            resolve(editor);
        });
    });
}
