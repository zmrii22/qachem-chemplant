<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
require_once __DIR__.'/../app/bootstrap.php';
$pdo=db_connect();
$pdo->exec("CREATE TABLE IF NOT EXISTS qachem_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration_name VARCHAR(190) NOT NULL UNIQUE, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$applied=[];foreach($pdo->query('SELECT migration_name,checksum FROM qachem_migrations')->fetchAll() as $r)$applied[$r['migration_name']]=$r['checksum'];
$files=array_merge(glob(__DIR__.'/../migrations/*.sql'),glob(__DIR__.'/../migrations/*.php'));sort($files);
foreach($files as $file){
  $name=basename($file);$sql=file_get_contents($file);$checksum=hash('sha256',$sql);
  // Some SQL exports include a UTF-8 BOM, which MySQL rejects before the first comment.
  $sql=preg_replace('/^\xEF\xBB\xBF/','',$sql);
  if(isset($applied[$name])){
    echo "Skipping $name (already applied).\n";
    $st=$pdo->prepare('INSERT IGNORE INTO qachem_migrations (migration_name,checksum) VALUES (?,?)');$st->execute([$name,$checksum]);
    continue;
  }
  echo "Running $name...\n";
  try{
    if(pathinfo($file,PATHINFO_EXTENSION)==='php')require $file;
    else $pdo->exec($sql);
    $st=$pdo->prepare('INSERT INTO qachem_migrations (migration_name,checksum) VALUES (?,?)');$st->execute([$name,$checksum]);
    echo "OK\n";
  }catch(Throwable $e){fwrite(STDERR,"FAILED: ".$e->getMessage()."\n");exit(1);}
}
require_once __DIR__.'/../app/TrackingStorage.php';
qc_tracking_storage($pdo);
echo "Migration complete.\n";
