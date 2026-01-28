require.config({
    paths: { vs: 'monaco/min/vs' }
});

window.editor = null;

require(['vs/editor/editor.main'], function () {
    window.editor = monaco.editor.create(
        document.getElementById('editor'),
        {
            value: [
                '# Python Web IDE',
                '# Schreibe deinen Code hier',
                '',
                'print("Hello, World!")'
            ].join('\n'),
            language: 'python',
            theme: 'vs-dark',
            automaticLayout: true,
            fontSize: 14,
            minimap: { enabled: false }
        }
    );
});
