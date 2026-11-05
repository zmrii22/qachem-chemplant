<?php
/** Additive storage for separate, linked Inprocess test runs. */
function qc_inprocess_run_storage(PDO $pdo): void {
    static $ready=false;
    if($ready)return;
    $columns=array_column($pdo->query('SHOW COLUMNS FROM t_qc_inprocess_batch')->fetchAll(),'Field');
    if(!in_array('run_no',$columns,true)){
        $pdo->exec('ALTER TABLE t_qc_inprocess_batch ADD run_no INT UNSIGNED NOT NULL DEFAULT 1, ADD parent_batch_id BIGINT UNSIGNED NULL, ADD root_batch_id BIGINT UNSIGNED NOT NULL DEFAULT 0, DROP INDEX batch_context, ADD UNIQUE KEY batch_context (plant,inspection_at,location_id,product_id,created_by_badge,root_batch_id,run_no), ADD UNIQUE KEY run_parent (parent_batch_id)');
    }
    $columns=array_column($pdo->query('SHOW COLUMNS FROM t_qc_inspection')->fetchAll(),'Field');
    if(!in_array('inprocess_batch_id',$columns,true)){
        $pdo->exec('ALTER TABLE t_qc_inspection ADD inprocess_batch_id BIGINT UNSIGNED NULL, ADD INDEX inprocess_run (inprocess_batch_id)');
        $pdo->exec("UPDATE t_qc_inspection i JOIN t_qc_inprocess_batch b ON b.root_batch_id=0 AND b.plant=i.plant AND b.inspection_at=i.inspection_at AND b.product_id=i.entity_id AND b.tank=i.sampling_location AND b.created_by_badge=i.created_by_badge AND BINARY b.batch_no=BINARY i.batch_no SET i.inprocess_batch_id=b.id,i.updated_at=i.updated_at WHERE i.inspection_type='INPROCESS' AND i.inprocess_batch_id IS NULL");
    }
    $ready=true;
}
