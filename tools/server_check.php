<?php
require_once __DIR__.'/../app/bootstrap.php';

echo "QACHEM server check\n";
echo "PHP: ".PHP_VERSION."\n";
echo "Folder: ".dirname(__DIR__)."\n";
echo "PDO MySQL: ".(extension_loaded('pdo_mysql')?'OK':'MISSING')."\n";
echo "mbstring: ".(extension_loaded('mbstring')?'OK':'Not installed; UTF-8 compatibility helpers active')."\n";
echo "Master JSON: ".(is_file(__DIR__.'/../data/qachem_master.json')?'OK':'MISSING data/qachem_master.json')."\n";

$cfg=qachem_config();
echo "DB host: ".($cfg['db']['host']??'')."\n";
echo "DB name: ".($cfg['db']['name']??'')."\n";
echo "DB user: ".(($cfg['db']['user']??'')!==''?'SET':'EMPTY')."\n";

try{
    $pdo=db_connect();
    echo "DB connection: OK\n";
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $needed=['m_user','qachem_migrations','t_qc_inspection','t_qc_result','t_qc_attachment','t_qc_inprocess_batch','t_qc_tracking','t_qc_product','t_qc_product_spec','t_qc_coa_remark','t_qc_product_coa_remark'];
    foreach($needed as $table){
        echo "Table $table: ".(in_array($table,$tables,true)?'OK':'MISSING')."\n";
    }
}catch(Throwable $e){
    echo "DB connection: FAILED\n";
    echo $e->getMessage()."\n";
}
