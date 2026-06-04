<?php
require __DIR__ . '/config/database.beta_live.local.php';
$res = runBetaLiveQuery("SHOW COLUMNS FROM tasks");
while($r=$res->fetch_assoc()){
  echo $r['Field'].' | '.$r['Type'].' | '.$r['Null'].' | '.($r['Default']===null?'NULL':$r['Default'])."\n";
}
