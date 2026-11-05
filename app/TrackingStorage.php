<?php
require_once __DIR__.'/bootstrap.php';

/** Repeatable setup and upgrade for the current Tracking schema. */
function qc_tracking_storage(PDO $pdo): void {
    static $ready=false;
    if($ready)return;
    if(!table_exists($pdo,'t_qc_inprocess_batch')||!table_exists($pdo,'t_qc_tracking')){
        $pdo->exec(file_get_contents(__DIR__.'/../migrations/002_batch_tracking.sql'));
    }
    $batchColumns=array_column($pdo->query('SHOW COLUMNS FROM t_qc_inprocess_batch')->fetchAll(),'Field');
    if(!in_array('preset_json',$batchColumns,true)){
        try{$pdo->exec('ALTER TABLE t_qc_inprocess_batch ADD COLUMN preset_json LONGTEXT NULL');}
        catch(PDOException $error){if(($error->errorInfo[1]??null)!==1060)throw $error;}
    }
    $trackingColumns=array_column($pdo->query('SHOW COLUMNS FROM t_qc_tracking')->fetchAll(),null,'Field');
    foreach(['planned_batch_no'=>'VARCHAR(100) NULL','modified_by_badge'=>'VARCHAR(60) NULL','modified_by_name'=>'VARCHAR(160) NULL','coa_generated_at'=>'DATETIME NULL','upload_date'=>'DATE NULL'] as $column=>$definition){
        if(!isset($trackingColumns[$column])){
            try{$pdo->exec('ALTER TABLE t_qc_tracking ADD COLUMN '.$column.' '.$definition);}
            catch(PDOException $error){if(($error->errorInfo[1]??null)!==1060)throw $error;}
        }
    }
    $changes=[];
    foreach(['source_batch_id','source_inspection_id'] as $column){
        if(!isset($trackingColumns[$column]))$changes[]='ADD COLUMN '.$column.' BIGINT UNSIGNED NULL, ADD INDEX ('.$column.')';
    }
    foreach(['plant'=>'VARCHAR(30)','product_id'=>'INT UNSIGNED','chemical_name'=>'VARCHAR(200)','batch_no'=>'VARCHAR(100)','source_inspection_at'=>'DATETIME','source_tank'=>'VARCHAR(80)','source_badge'=>'VARCHAR(60)'] as $column=>$type){
        if($trackingColumns[$column]['Null']==='NO')$changes[]='MODIFY COLUMN '.$column.' '.$type.' NULL';
    }
    if($changes){
        $pdo->exec('ALTER TABLE t_qc_tracking '.implode(', ',$changes));
        // Resolve legacy source links before clearing copied display values.
        $pdo->beginTransaction();
        try{
            $pdo->exec("UPDATE t_qc_tracking t JOIN t_qc_inprocess_batch b ON b.product_id=t.product_id AND b.plant=t.plant AND b.inspection_at=t.source_inspection_at AND b.tank=t.source_tank AND b.created_by_badge=t.source_badge SET t.source_batch_id=b.id WHERE t.source_batch_id IS NULL");
            $pdo->exec("UPDATE t_qc_tracking t SET source_inspection_id=(SELECT i.id FROM t_qc_inspection i WHERE i.inspection_type='INPROCESS' AND i.status<>'CANCELLED' AND i.entity_id=t.product_id AND i.plant=t.plant AND i.inspection_at=t.source_inspection_at AND i.sampling_location=t.source_tank AND i.created_by_badge=t.source_badge ORDER BY i.id DESC LIMIT 1) WHERE t.source_batch_id IS NULL AND t.source_inspection_id IS NULL");
            $pdo->exec("UPDATE t_qc_tracking SET plant=NULL,product_id=NULL,chemical_name=NULL,batch_no=NULL,source_inspection_at=NULL,source_tank=NULL,source_badge=NULL,testing_date=NULL,details_json=JSON_REMOVE(details_json,'$.source_key','$.expiry_date') WHERE source_batch_id IS NOT NULL OR source_inspection_id IS NOT NULL");
            $pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
    }
    if(table_exists($pdo,'t_qc_coa')){
        if(isset($trackingColumns['coa_id'])){
            $archiveColumns=array_column($pdo->query('SHOW COLUMNS FROM t_qc_coa')->fetchAll(),'Field');
            $upload=in_array('upload_date',$archiveColumns,true)?',t.upload_date=c.upload_date':'';
            // Retain only current status/date metadata. Stored documents and versions are removed.
            $pdo->exec('UPDATE t_qc_tracking t JOIN t_qc_coa c ON c.id=t.coa_id SET t.coa_generated_at=c.generated_at'.$upload.',t.updated_at=t.updated_at');
        }
        $pdo->exec("DELETE FROM t_qc_attachment WHERE entity_type='COA'");
        $pdo->exec('DROP TABLE t_qc_coa');
    }
    $obsolete=[];
    foreach(['coa_id','coa_revision','snapshot_json','verified_by_badge','verified_by_name','verified_at'] as $column){
        if(isset($trackingColumns[$column]))$obsolete[]='DROP COLUMN '.$column;
    }
    if($obsolete)$pdo->exec('ALTER TABLE t_qc_tracking '.implode(', ',$obsolete));
    if(!isset($trackingColumns['modified_by_name']))$pdo->exec('UPDATE t_qc_tracking SET modified_by_badge=created_by_badge,modified_by_name=created_by_name,updated_at=updated_at WHERE modified_by_name IS NULL');
    require_once __DIR__.'/InprocessRunStorage.php';
    qc_inprocess_run_storage($pdo);
    $ready=true;
}
