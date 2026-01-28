<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Monaco AMD Test</title>

  <style>
    html, body {
      margin: 0;
      padding: 0;
      height: 100%;
    }
    #editor {
      width: 100%;
      height: 100vh;
    }
  </style>
</head>
<body>

  <div id="editor"></div>

  <!-- Monaco AMD Loader -->
  <script src="./monaco/min/vs/loader.js"></script>

  <!-- Deine IDE-Logik -->
  <script src="./js/ide.js"></script>

</body>
</html>
