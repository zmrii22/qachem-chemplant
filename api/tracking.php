<?php
require_once __DIR__.'/../app/Tracking.php';
$user=require_login();$pdo=db_connect();
try{
    qc_tracking_storage($pdo);
    if($_SERVER['REQUEST_METHOD']==='GET'){
        if(($_GET['action']??'')==='export'){
            require_once __DIR__.'/../app/TrackingExcel.php';
            $file=TrackingExcel::create(Tracking::records($pdo,[],null));
            try{
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="Tracking_Record_QA_'.date('Y-m-d_His').'.xlsx"');
                header('Content-Length: '.filesize($file));
                header('Cache-Control: private, no-store');
                header('X-Content-Type-Options: nosniff');
                readfile($file);
            }finally{unlink($file);}
            exit;
        }
        if(($_GET['action']??'')==='sources')json_response(['success'=>true,'sources'=>Tracking::sources($pdo)]);
        if(($_GET['action']??'')==='source')json_response(['success'=>true,'source'=>Tracking::source($pdo,(string)($_GET['key']??''))]);
        if(($_GET['action']??'')==='specifications'){
            $source=Tracking::source($pdo,(string)($_GET['source_key']??''));
            $specifications=Tracking::coaSpecifications($pdo,(int)$source['product_id']);
            json_response(['success'=>true,'specifications'=>$specifications,'default_profile_id'=>Tracking::defaultCoaSpecification($pdo,$source)]);
        }
        if(!empty($_GET['id'])){
            $record=Tracking::get($pdo,(int)$_GET['id']);
            $document=null;$documentError=null;
            if(!$record['source_missing'])try{$document=Tracking::document($pdo,$record);}catch(InvalidArgumentException $error){$documentError=$error->getMessage();}
            json_response(['success'=>true,'record'=>$record,'document'=>$document,'document_error'=>$documentError]);
        }
        json_response(['success'=>true,'rows'=>iterator_to_array(Tracking::records($pdo,$_GET),false)]);
    }
    verify_csrf();$data=json_input();$action=$data['action']??'save';
    if(in_array($action,['preview','generate'],true)&&array_key_exists('coa_spec_profile_id',$data)&&!Tracking::coaSpecificationId($data['coa_spec_profile_id']))throw new InvalidArgumentException('Choose a specification.');
    if($action==='preview'){
        $source=Tracking::source($pdo,(string)($data['source_key']??''));
        $data['inspector']=$user['name'];
        $record=['source_batch_id'=>$source['source_batch_id']??null,'source_inspection_id'=>$source['source_inspection_id']??null,'product_id'=>$source['product_id'],'plant'=>$source['plant'],'source_inspection_at'=>$source['inspection_at'],'source_tank'=>$source['tank'],'source_badge'=>$source['created_by_badge'],'batch_no'=>$source['batch_no'],'factory'=>$data['factory']??'','manufacturing_date'=>Tracking::date($data['manufacturing_date']??substr($source['inspection_at'],0,10)),'po_no'=>$data['po_no']??'','packaging_no'=>$data['packaging_no']??'','remark'=>$data['remark']??'','details'=>$data];
        json_response(['success'=>true,'source'=>$source,'document'=>Tracking::document($pdo,$record)]);
    }
    require_permission($action==='save'?'tracking_plan':($action==='generate'?'generate_coa':'tracking_send'));
    // Planners can prepare records, but sending dates are managed by editors.
    if(!user_can('tracking_send',$user)) unset($data['upload_date']);
    $pdo->beginTransaction();
    if($action==='save'){
        $id=Tracking::save($pdo,$data,$user);
        if(array_key_exists('upload_date',$data))Tracking::saveUploadDate($pdo,Tracking::get($pdo,$id),(string)($data['upload_date']??''),$user);
        $pdo->commit();
        $record=Tracking::get($pdo,$id);
        json_response(['success'=>true,'id'=>$id,'record'=>$record]);
    }
    if($action==='upload_date'){
        $record=Tracking::get($pdo,(int)($data['id']??0),true);
        if(isset($data['updated_at'])&&(string)$data['updated_at']!==$record['updated_at'])throw new InvalidArgumentException('Record changed. Reload and try again.');
        Tracking::saveUploadDate($pdo,$record,(string)($data['upload_date']??''),$user);
        $pdo->commit();json_response(['success'=>true,'record'=>Tracking::get($pdo,$record['id'])]);
    }
    if($action!=='generate')throw new InvalidArgumentException('Unknown tracking action.');
    $id=array_key_exists('source_key',$data)?Tracking::save($pdo,$data,$user):(int)($data['id']??0);
    $record=Tracking::get($pdo,$id,true);$document=Tracking::document($pdo,$record);
    if($document['problems'])throw new InvalidArgumentException(implode(' ', $document['problems']));
    $pdo->prepare("UPDATE t_qc_tracking SET status='GENERATED',coa_generated_at=NOW(),modified_by_badge=?,modified_by_name=?,updated_at=NOW() WHERE id=?")->execute([$user['badge'],$user['name'],$id]);
    if(array_key_exists('upload_date',$data))Tracking::saveUploadDate($pdo,Tracking::get($pdo,$id),(string)($data['upload_date']??''),$user);
    $pdo->commit();json_response(['success'=>true,'tracking_id'=>$id,'url'=>'COAForm.php?tracking_id='.$id,'record'=>Tracking::get($pdo,$id)]);
}catch(InvalidArgumentException $error){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$error->getMessage()],422);}
catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();error_log('tracking: '.$error->getMessage());json_response(['success'=>false,'message'=>'Unable to complete the tracking request.'],500);}
