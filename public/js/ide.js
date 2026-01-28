require.config({
  paths: {
    vs: './monaco/min/vs'
  }
});

require(['vs/editor/editor.main'], function () {

  const editor = monaco.editor.create(
    document.getElementById('editor'),
    {
      value: [
        'def hello():',
        '    print("Hello Monaco!")',
        '',
        'hello()'
      ].join('\n'),
      language: 'python',
      theme: 'vs-dark',
      automaticLayout: true
    }
  );

});
