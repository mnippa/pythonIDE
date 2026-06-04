<?php
require __DIR__ . '/config/database.beta_live.local.php';
$res = runBetaLiveQuery("SELECT id, task_type, title, file_submission_allowed_types, file_submission_max_size_bytes, manual_review_required FROM tasks WHERE id=403");
$row = $res->fetch_assoc();
var_export($row);
