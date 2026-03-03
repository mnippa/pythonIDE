/*
  Task #21 Runtime-Hinweis (readonly empfohlen)
  ---------------------------------------------
  Diese Datei dokumentiert die Ereignislogik für Code-UI-Tasks.

  Aktueller Ablauf im System:
  1) Button mit data-run-python="true" wird geklickt.
  2) Die Plattform fängt das Ereignis zentral ab (public/js/assignments.js).
  3) Der globale Run-Button (#run-btn) wird ausgelöst.
  4) Python-Code (init.py) wird ausgeführt und schreibt mit idegui in data-output.

  Warum zentral?
  - Einheitliches Verhalten für alle Code-UI-Tasks
  - Schüler brauchen kein eigenes JavaScript
  - Aufgaben bleiben auf Python + HTML fokussiert

  Optionaler Fallback (nur Doku, standardmäßig nicht aktiv):
  ----------------------------------------------------------
  Wenn ihr die Logik lokal im Task sehen wollt, kann man hier
  ebenfalls einen Click-Handler hinterlegen, der #run-btn klickt.
*/

(function () {
  const enabled = false; // auf true setzen, wenn lokaler Fallback gewünscht ist
  if (!enabled) return;

  document.addEventListener('click', (event) => {
    const trigger = event.target?.closest?.('[data-run-python="true"]');
    if (!trigger) return;
    event.preventDefault();
    const runButton = document.getElementById('run-btn');
    if (runButton) runButton.click();
  });
})();
