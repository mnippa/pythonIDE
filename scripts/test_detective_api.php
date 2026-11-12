<?php
declare(strict_types=1);

// Execute the endpoint against an in-memory SQL double, never the configured database.
final class DetectiveResult {
    public int $num_rows;
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; $this->num_rows = count($rows); }
    public function fetch_assoc(): ?array { return $this->rows[0] ?? null; }
    public function fetch_all(int $mode): array { return $this->rows; }
}
final class DetectiveStatement {
    private array $params = [];
    private array $rows = [];
    private DetectiveConnection $conn;
    private string $sql;
    public function __construct(DetectiveConnection $conn, string $sql) { $this->conn = $conn; $this->sql = $sql; }
    public function bind_param(string $types, &...$params): void { $this->params = &$params; }
    public function execute(): bool {
        $p = $this->params;
        if (strpos($this->sql, 'FROM tasks t') !== false) {
            $this->rows = $p[1] === 42
                ? [['task_type' => 'code', 'test_cases' => '[{"type":"code_check","keywords":["for"]}]']]
                : [];
        } elseif (strpos($this->sql, 'FROM users') !== false) {
            check(strpos($this->sql, 'FOR UPDATE') !== false, 'quota is serialized with a row lock');
            $this->rows = [['id' => $p[0]]];
        } elseif (strpos($this->sql, 'SELECT fingerprint') !== false) {
            $this->rows = array_values(array_filter($this->conn->reports, fn($r) => $r['user_id'] === $p[0] && $r['task_id'] === $p[1]));
        } elseif (strpos($this->sql, 'INSERT INTO solution_detective_reports') !== false) {
            $this->conn->reports[] = [
                'user_id' => $p[0], 'task_id' => $p[1], 'fingerprint' => $p[2],
                'report_json' => $p[3], 'created_at' => '2026-11-03 12:00:00'
            ];
        } else {
            throw new RuntimeException('Unexpected SQL: ' . $this->sql);
        }
        return true;
    }
    public function get_result(): DetectiveResult { return new DetectiveResult($this->rows); }
}
final class DetectiveConnection {
    public array $reports = [];
    public bool $installed = true;
    public bool $failInsert = false;
    private array $snapshot = [];
    public function prepare(string $sql): DetectiveStatement {
        if ($this->failInsert && strpos($sql, 'INSERT') !== false) throw new RuntimeException('Simulated storage error');
        return new DetectiveStatement($this, $sql);
    }
    public function query(string $sql): DetectiveResult {
        if (strpos($sql, 'SHOW TABLES') === false) throw new RuntimeException('Unexpected query');
        return new DetectiveResult($this->installed ? [['table' => 'solution_detective_reports']] : []);
    }
    public function begin_transaction(): void { $this->snapshot = $this->reports; }
    public function commit(): void {}
    public function rollback(): void { $this->reports = $this->snapshot; }
}
function requireAuth(): array { return $GLOBALS['detectiveUser']; }
function getDbConnection(): DetectiveConnection { return $GLOBALS['detectiveDb']; }
function detectiveTestResponse(array $data, int $status = 200): array { return ['status' => $status] + $data; }
if (!defined('MYSQLI_ASSOC')) define('MYSQLI_ASSOC', 1);
$detectiveDb = new DetectiveConnection();
$detectiveUser = ['id' => 7, 'role' => 'user'];
$source = file_get_contents(__DIR__ . '/../api/user_tasks/detective.php');
$source = preg_replace('/^<\?php\s*declare\(strict_types=1\);/', '', $source);
$source = preg_replace('/^require_once .*;\s*$/m', '', $source);
$source = str_replace("header('Content-Type: application/json; charset=utf-8');", '', $source);
$source = str_replace("file_get_contents('php://input')", '$GLOBALS["detectiveInput"]', $source);
$source = str_replace('jsonResponse(', 'return detectiveTestResponse(', $source);
function callDetective(string $method, array $input): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $input;
    $GLOBALS['detectiveInput'] = json_encode($input);
    return eval($GLOBALS['source']);
}
function check(bool $value, string $label): void {
    if (!$value) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
function payload(string $char): array {
    return ['task_id' => 42, 'fingerprint' => str_repeat($char, 64), 'report' => ['messages' => ['Konkrete Einschätzung']]];
}
$r = callDetective('GET', ['task_id' => 42]);
check($r['remaining'] === 2, 'new task starts with two uses');
$r = callDetective('GET', ['task_id' => 42, 'admin_test' => 1]);
check($r['status'] === 403, 'students cannot request unlimited admin mode');
$r = callDetective('POST', payload('a'));
check($r['remaining'] === 1 && count($detectiveDb->reports) === 1, 'completed diagnosis consumes one use');
$r = callDetective('POST', payload('a'));
check($r['remaining'] === 1 && count($detectiveDb->reports) === 1, 'same fingerprint is idempotent');
$r = callDetective('POST', payload('b') + ['current_iteration' => 8]);
check($r['remaining'] === 0, 'iterations share the task quota');
$r = callDetective('POST', payload('c'));
check($r['status'] === 409 && count($detectiveDb->reports) === 2, 'third distinct diagnosis cannot be saved');
$r = callDetective('GET', ['task_id' => 42]);
check(count($r['reports']) === 2, 'saved reports remain readable after exhaustion');
$detectiveUser['id'] = 8;
$r = callDetective('GET', ['task_id' => 42]);
check($r['remaining'] === 2 && $r['reports'] === [], 'users have independent quotas and report privacy');
$detectiveDb->failInsert = true;
$r = callDetective('POST', payload('a'));
check($r['status'] === 500 && count($detectiveDb->reports) === 2, 'storage failure rolls back without consuming a use');
$detectiveDb->failInsert = false;
$detectiveDb->installed = false;
$r = callDetective('GET', ['task_id' => 42]);
check($r['status'] === 503, 'missing migration is explicit');
$detectiveUser['role'] = 'admin';
foreach (['lab', 'student-view'] as $view) {
    for ($i = 0; $i < 3; $i++) {
        $r = callDetective('POST', payload('c') + ['admin_test' => 1, 'test_user_id' => 7, 'view' => $view]);
        check($r['unlimited'] && $r['remaining'] === null && count($detectiveDb->reports) === 2, "$view admin diagnosis is unlimited and never writes student data");
    }
}
$detectiveDb->installed = true;
$r = callDetective('GET', ['task_id' => 42]);
check(!$r['unlimited'] && $r['remaining'] === 2, 'admin in normal participant view retains the regular quota');
$r = callDetective('POST', ['task_id' => 42, 'fingerprint' => 'invalid', 'report' => ['messages' => []]]);
check($r['status'] === 400, 'invalid report is rejected even for admins');
$r = callDetective('GET', ['task_id' => 99]);
check($r['status'] === 404, 'unknown task is rejected');
