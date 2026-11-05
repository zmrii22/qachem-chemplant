<?php
require_once __DIR__.'/../app/InprocessBatch.php';
$user=require_permission('rawform');verify_csrf();
$pdo=db_connect();
try{
    qc_tracking_storage($pdo);$pdo->beginTransaction();
    $saved=InprocessBatch::save($pdo,json_input(),$user);
    $pdo->commit();json_response(['success'=>true]+$saved);
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$error->getMessage()],422);}
catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();error_log('batch save: '.$error->getMessage());json_response(['success'=>false,'message'=>'Unable to save batch number.'],500);}
