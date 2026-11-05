<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
require_once __DIR__.'/../app/bootstrap.php';
$checks=[];
$checks[]=['PHP >= 7.4',version_compare(PHP_VERSION,'7.4.0','>=')];
foreach(['pdo','pdo_mysql','json','fileinfo'] as $ext)$checks[]=["Extension $ext",extension_loaded($ext)];
echo extension_loaded('mbstring')?"[OK] Extension mbstring\n":"[OK] UTF-8 compatibility helpers (mbstring not installed)\n";
$cfg=qachem_config('db');$checks[]=['Database name configured',trim((string)$cfg['name'])!==''];$checks[]=['Database user configured',trim((string)$cfg['user'])!==''];
$ok=true;foreach($checks as [$label,$pass]){echo ($pass?'[OK] ':'[FAIL] ').$label."\n";$ok=$ok&&$pass;}
try{$pdo=db_connect();echo "[OK] Database connection\n";foreach(['m_user','qachem_migrations','t_qc_inspection','t_qc_result','t_qc_attachment','t_qc_inprocess_batch','t_qc_tracking','t_qc_product','t_qc_product_spec','t_qc_coa_remark','t_qc_product_coa_remark'] as $t){$exists=table_exists($pdo,$t);echo ($exists?'[OK] ':'[MISSING] ')."Table $t\n";$ok=$ok&&$exists;}foreach(['qachem_master.json','tracking_reference.json'] as $file){$exists=is_file(__DIR__.'/../data/'.$file);echo ($exists?'[OK] ':'[MISSING] ')."Reference data data/$file\n";$ok=$ok&&$exists;}}catch(Throwable $e){echo "[FAIL] Database connection: ".$e->getMessage()."\n";$ok=false;}
exit($ok?0:1);
