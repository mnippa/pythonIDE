<?php
session_start();

/* Login-Prüfung (vorerst optional deaktivierbar)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
*/
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Python Web IDE</title>

    <link rel="stylesheet" href="css/ide.css">

    <!-- Monaco Loader -->
    <script src="monaco/min/vs/loader.js"></script>
</head>
<body>

<header>
    <h1>Python Web IDE</h1>
</header>

<main>
    <div id="editor"></div>
</main>

<script src="js/editor.js"></script>
<script src="js/ide.js"></script>

</body>
</html>
