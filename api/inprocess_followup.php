<?php
require_once __DIR__.'/../app/InprocessRuns.php';
$user=require_permission('rawform');verify_csrf();$pdo=db_connect();
try{
    qc_tracking_storage($pdo);$data=json_input();
    if(($data['action']??'')==='preview'){
        $run=InprocessRuns::get($pdo,(int)($data['run_id']??0));InprocessRuns::assertLatest($pdo,$run);
        $records=InprocessRuns::records($pdo,(int)$run['id']);
        json_response(['success'=>true,'source_token'=>InprocessRuns::fingerprint($records),'records'=>$records]);
    }
    $pdo->beginTransaction();
    $result=InprocessRuns::save($pdo,$data,$user);
    $pdo->commit();json_response(['success'=>true]+$result);
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$error->getMessage()],422);}
catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();error_log('Inprocess follow-up: '.$error->getMessage());json_response(['success'=>false,'message'=>'Unable to create the follow-up. Please retry.'],500);}
