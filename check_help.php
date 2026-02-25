<?php
$data = json_decode(file_get_contents('storage/help/help.json'), true) ?? [];
$npFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'np.') === 0);
$pltFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'plt.') === 0);
$mathFuncs = array_filter(array_keys($data), fn($k) => strpos($k, 'math.') === 0);

echo "Total Entries: " . count($data) . "\n";
echo "  NumPy (np.*): " . count($npFuncs) . "\n";
echo "  Matplotlib (plt.*): " . count($pltFuncs) . "\n";
echo "  Math (math.*): " . count($mathFuncs) . "\n";

if (count($npFuncs) > 0) {
  echo "\nFirst 5 np. entries:\n";
  foreach (array_slice($npFuncs, 0, 5) as $f) echo "  - $f\n";
}

if (count($pltFuncs) > 0) {
  echo "\nFirst 5 plt. entries:\n";
  foreach (array_slice($pltFuncs, 0, 5) as $f) echo "  - $f\n";
}
?>
