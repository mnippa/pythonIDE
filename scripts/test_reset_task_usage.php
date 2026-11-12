<?php
declare(strict_types=1);

final class ResetResult {
    private ?array $row;
    public function __construct(?array $row) { $this->row = $row; }
    public function fetch_assoc(): ?array { return $this->row; }
}
final class ResetStatement {
    private array $params = [];
    private ResetConnection $db;
    private string $sql;
    public function __construct(ResetConnection $db, string $sql) { $this->db = $db; $this->sql = $sql; }
    public function bind_param(string $types, &...$params): void { $this->params = &$params; }
    public function execute(): bool {
        $this->db->queries[] = [$this->sql, $this->params];
        if (strpos($this->sql, 'UPDATE') === 0) {
            $this->db->attempts[$this->params[0] . ':' . $this->params[1]] = 0;
        }
        if (strpos($this->sql, 'DELETE') === 0) {
            if ($this->db->failDelete) throw new RuntimeException('Simulated delete failure');
            $this->db->reports = array_values(array_filter($this->db->reports,
                fn($r) => $r !== $this->params[0] . ':' . $this->params[1]));
        }
        return true;
    }
    public function get_result(): ResetResult {
        if (strpos($this->sql, 'FROM users') !== false) return new ResetResult(['id' => 7]);
        return new ResetResult($this->params === [42, 49, 7] ? ['id' => 42] : null);
    }
}
final class ResetConnection {
    public array $queries = [];
    public array $reports = ['7:42', '7:42', '8:42', '7:43'];
    public array $attempts = ['7:42' => 5, '8:42' => 4];
    public bool $failDelete = false;
    public bool $rolledBack = false;
    public function prepare(string $sql): ResetStatement { return new ResetStatement($this, $sql); }
    public function begin_transaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollback(): void { $this->rolledBack = true; }
}
function requireAdmin(): array {
    if (!$GLOBALS['isAdmin']) throw new RuntimeException('Forbidden');
    return ['id' => 1];
}
function requireAdminOwnedAssignment($db, $id, $admin): void {
    if (!$GLOBALS['owned']) throw new RuntimeException('Not owned');
}
function getDbConnection(): ResetConnection { return $GLOBALS['db']; }
function resetResponse(array $data, int $status = 200): array { return ['status' => $status] + $data; }
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$source = file_get_contents(__DIR__ . '/../api/admin/assignments/users/reset-task-usage.php');
$source = preg_replace('/^<\?php\s*declare\(strict_types=1\);/', '', $source);
$source = preg_replace('/^(?:require_once|header\().*;\s*$/m', '', $source);
$source = str_replace("file_get_contents('php://input')", '$GLOBALS["body"]', $source);
$source = str_replace('jsonResponse(', 'return resetResponse(', $source);
$db = new ResetConnection();
$isAdmin = true;
$owned = true;
function callReset(array $input, string $method = 'POST'): array {
    $_SERVER['REQUEST_METHOD'] = $method;
    $GLOBALS['body'] = json_encode($input);
    return eval($GLOBALS['source']);
}
$base = ['assignment_id' => 49, 'user_id' => 7, 'task_id' => 42];
check(callReset($base + ['usage' => 'checks'], 'GET')['status'] === 405, 'GET cannot reset');
check(callReset($base + ['usage' => 'invalid'])['status'] === 400, 'invalid usage rejected');
check(callReset(['assignment_id' => 50, 'user_id' => 7, 'task_id' => 42, 'usage' => 'detective'])['status'] === 404, 'assignment membership checked');
check(callReset($base + ['usage' => 'checks'])['ok'], 'checks reset succeeds');
check($db->attempts === ['7:42' => 0, '8:42' => 4] && count($db->reports) === 4, 'checks reset only selected task and preserves reports');
check(callReset($base + ['usage' => 'detective'])['ok'], 'detective reset succeeds');
check($db->reports === ['8:42', '7:43'], 'detective reset preserves other users and tasks');
check(callReset($base + ['usage' => 'detective'])['ok'], 'reset is idempotent');
$db->failDelete = true;
check(callReset($base + ['usage' => 'detective'])['status'] === 500 && $db->rolledBack, 'storage errors surfaced and rolled back');
foreach ($db->queries as [$sql]) {
    check(strpos($sql, 'SET status') === false && strpos($sql, 'current_code') === false, 'no status or code writes');
}
foreach (['isAdmin', 'owned'] as $guard) {
    $GLOBALS[$guard] = false;
    $denied = false;
    try { callReset($base + ['usage' => 'checks']); } catch (RuntimeException $e) { $denied = true; }
    check($denied, "$guard guard required");
    $GLOBALS[$guard] = true;
}
