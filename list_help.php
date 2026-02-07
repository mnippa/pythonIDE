<?php
$data = json_decode(file_get_contents('c:\xampp\htdocs\pythonIDE\storage\help\help.json'), true) ?? [];

$npFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'np.') === 0);
$pltFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'plt.') === 0);
$mathFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'math.') === 0);
$strFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'str.') === 0);
$listFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'list.') === 0);

echo "Total Entries: " . count($data) . "\n";
echo "  NumPy: " . count($npFuncs) . "\n";
echo "  Matplotlib: " . count($pltFuncs) . "\n";
echo "  Math: " . count($mathFuncs) . "\n";
echo "  String: " . count($strFuncs) . "\n";
echo "  List: " . count($listFuncs) . "\n";

if (count($mathFuncs) > 0) {
  sort($mathFuncs);
  echo "\nMath functions:\n";
  foreach (array_slice($mathFuncs, 0, 10) as $f) {
    echo "  $f\n";
  }
}

if (count($strFuncs) > 0) {
  sort($strFuncs);
  echo "\nString functions:\n";
  foreach (array_slice($strFuncs, 0, 10) as $f) {
    echo "  $f\n";
  }
}

