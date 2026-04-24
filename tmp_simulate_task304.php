<?php
require __DIR__ . '/config/database.php';
$conn = getDbConnection();
$stmt = $conn->prepare('SELECT solution_code, randomizer_code FROM tasks WHERE id = 304');
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) {
    echo "TASK_NOT_FOUND\n";
    exit(1);
}
$solution = $row['solution_code'];
$randomizer = $row['randomizer_code'];

$py = <<<'PY'
import json

solution_code = __SOLUTION__
randomizer_code = __RANDOMIZER__

namespace = {}
exec(compile(solution_code, '<code>', 'exec'), namespace)
print('INITIAL_NAMEN=' + repr(namespace.get('namen')))

rand_ns = {}
exec(compile(randomizer_code, '<randomizer>', 'exec'), rand_ns)
values = rand_ns['values']
print('VALUES=' + repr(values))

namespace.update(values)
print('AFTER_UPDATE=' + repr(namespace.get('namen')))

init_block_end = solution_code.find('#INIT END')
print('INIT_INDEX=' + repr(init_block_end))
calculation_code = solution_code[init_block_end + len('#INIT END'):].strip()
print('CALCULATION_START')
print(calculation_code)
print('CALCULATION_END')
exec(compile(calculation_code, '<calculation>', 'exec'), namespace)
print('AFTER_REEXEC=' + repr(namespace.get('namen')))
PY;

$py = str_replace('__SOLUTION__', var_export($solution, true), $py);
$py = str_replace('__RANDOMIZER__', var_export($randomizer, true), $py);

$tmpPy = __DIR__ . '/tmp_simulate_task304_inner.py';
file_put_contents($tmpPy, $py);
passthru('python ' . escapeshellarg($tmpPy), $code);
@unlink($tmpPy);
exit($code);
