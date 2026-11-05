<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/spec.php';
require_once __DIR__.'/../app/LinePreset.php';
require_once __DIR__.'/../app/IncomingForm.php';
require_once __DIR__.'/../include/incoming_signoff.php';
require_once __DIR__.'/../app/InprocessRuns.php';
$u=require_login();
try{$pdo=db_connect();}catch(Throwable $e){json_response(['success'=>false,'message'=>'Database unavailable.'],500);}

function inspection_number(string $type): string {
    $p=['INCOMING'=>'IN','INPROCESS'=>'IP'][$type]??'QC';
    return $p.'-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
}
function valid_date_or_null($v){$v=trim((string)$v);return $v===''?null:$v;}
function build_results(PDO $pdo,array $profile,array $payload,int $attempt,array $u,bool $onlyIncomingRules=false): array {
    $incoming=$payload['results']??[]; if(!is_array($incoming))$incoming=[];
    $byRule=[]; foreach($incoming as $r){if(isset($r['rule_id']))$byRule[(string)$r['rule_id']]=$r;}
    $rules=$profile['rules'];
    if($onlyIncomingRules){
        $rules=array_values(array_filter($rules,fn($rule)=>isset($byRule[(string)$rule['id']])));
    }
    $out=[];
    foreach($rules as $rule){
        if(($rule['operator']??'')==='INFO') continue;
        $ir=$byRule[(string)$rule['id']]??[];
        $samples=$ir['samples']??[]; if(!is_array($samples))$samples=[];
        $samples=array_values(array_filter($samples,fn($v)=>$v!==''&&$v!==null));
        $value=null;$text=null;
        if(($rule['data_type']??'NUMERIC')==='NUMERIC'){
            $nums=array_values(array_filter($samples,'is_numeric'));
            if($nums) $value=array_sum(array_map('floatval',$nums))/count($nums);
            elseif(isset($ir['value'])&&is_numeric($ir['value']))$value=(float)$ir['value'];
        }else{$text=trim((string)($ir['text']??($samples[0]??'')));}
        $disp=evaluate_rule($rule,$value,$text);
        if(strtoupper((string)($payload['inspection_type']??''))==='INPROCESS')$disp=InprocessGrid::disposition($disp);
        $rawInput=['samples'=>$samples,'raw_samples'=>$ir['raw_samples']??null,'calculator'=>$ir['calculator']??null];
        if(isset($payload['_incoming_snapshot']))$rawInput['samples']=$ir['samples']??[];
        if(isset($payload['_line_preset_snapshot']))$rawInput['line_preset_snapshot']=$payload['_line_preset_snapshot'];
        if(isset($payload['_incoming_snapshot']))$rawInput['incoming_snapshot']=$payload['_incoming_snapshot'];
        if(isset($payload['_incoming_form']))$rawInput['incoming_form']=$payload['_incoming_form'];
        $out[]=['spec_rule_id'=>$rule['id'],'parameter_code'=>$rule['parameter_code'],'parameter_name'=>$rule['parameter_name'],'result_value'=>$value,'result_text'=>$text,'unit'=>$rule['unit'],'disposition'=>$disp,'attempt_no'=>$attempt,'raw_input_json'=>json_encode($rawInput,JSON_UNESCAPED_UNICODE),'created_by_badge'=>$u['badge'],'created_by_name'=>$u['name']];
    }
    return $out;
}
function overall_from_results(array $results): string {
    $d=array_column($results,'disposition');
    if(in_array('FAIL',$d,true))return 'FAIL';
    if(in_array('REVIEW',$d,true)||in_array('PENDING',$d,true))return 'HOLD';
    return $results?'PASS':'HOLD';
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    try{
        if(!empty($_GET['id'])){
            $st=$pdo->prepare('SELECT * FROM t_qc_inspection WHERE id=?');$st->execute([(int)$_GET['id']]);$row=$st->fetch();
            if(!$row||!in_array($row['inspection_type'],['INCOMING','INPROCESS'],true))json_response(['success'=>false,'message'=>'Inspection not found.'],404);
            if($row['inspection_type']==='INPROCESS')require_permission('rawform');
            $rr=$pdo->prepare('SELECT * FROM t_qc_result WHERE inspection_id=? ORDER BY attempt_no,id');$rr->execute([$row['id']]);
            json_response(['success'=>true,'inspection'=>$row,'results'=>$rr->fetchAll()]);
        }
        $type=strtoupper(trim($_GET['type']??''));
        if(!in_array($type,['INCOMING','INPROCESS'],true))json_response(['success'=>false,'message'=>'Invalid type.'],400);
        $st=$pdo->prepare('SELECT * FROM t_qc_inspection WHERE inspection_type=? ORDER BY inspection_at DESC,id DESC LIMIT 100');$st->execute([$type]);
        json_response(['success'=>true,'rows'=>$st->fetchAll()]);
    }catch(Throwable $e){error_log('inspection GET: '.$e->getMessage());json_response(['success'=>false,'message'=>'Unable to load inspection data.'],500);}
}

require_permission('edit');verify_csrf();
$d=json_input();$action=strtolower($d['action']??'save');
try{
    if($action!=='save')json_response(['success'=>false,'message'=>'This action is no longer available.'],410);

    $type=strtoupper(trim($d['inspection_type']??''));$entityType=strtoupper(trim($d['entity_type']??''));$entityId=(int)($d['entity_id']??0);$specType=strtoupper(trim($d['spec_type']??''));
    if($type==='INPROCESS')require_permission('rawform');
    if(!in_array($type,['INCOMING','INPROCESS'],true)||!in_array($entityType,['PRODUCT','RAW_MATERIAL'],true)||$entityId<1||!in_array($specType,['IQS','QS','INCOMING'],true)) json_response(['success'=>false,'message'=>'Required inspection fields are invalid.'],422);
    if($type==='INCOMING'&&($entityType!=='RAW_MATERIAL'||$specType!=='INCOMING'))json_response(['success'=>false,'message'=>'Incoming inspections must use a raw-material incoming specification.'],422);
    if($type==='INPROCESS'&&($entityType!=='PRODUCT'||!in_array($specType,['IQS','QS'],true)))json_response(['success'=>false,'message'=>'In-process inspections must use a product IQS or QS profile.'],422);
    $plant=strtoupper(trim((string)($d['plant']??'')));$factory=qc_normalize_factory(trim((string)($d['factory']??'')));$supplier='';$chemical='';
    if($entityType==='PRODUCT'){$entity=qc_master_product_by_id($entityId);if(!$entity)json_response(['success'=>false,'message'=>'Selected product is inactive or does not exist.'],422);$chemical=$entity['product_name'];}
    else{$entity=qc_master_raw_material_by_id($entityId);if(!$entity)json_response(['success'=>false,'message'=>'Selected raw material is inactive or does not exist.'],422);$chemical=$entity['material_name'];$supplier=trim((string)$entity['supplier']);}
    if($type!=='INCOMING'){
        if(!in_array($plant,['F21RBD','F40ARP'],true))json_response(['success'=>false,'message'=>'Select a valid source plant.'],422);
        if($factory!==''&&!qc_master_factory_exists($factory))json_response(['success'=>false,'message'=>'Destination factory is not in the active factory master.'],422);
        $loc=trim((string)($d['sampling_location']??''));if($loc!==''&&!qc_master_location_exists($plant,$loc))json_response(['success'=>false,'message'=>'Sampling tank/reactor does not belong to the selected plant.'],422);
    }else{$plant='';$factory='';}
    $profile=get_spec_profile($pdo,$entityType,$entityId,$specType,$factory,$supplier);
    if($type==='INCOMING'&&($d['source']??'')==='INCOMING_FORM'){
        IncomingForm::validateDetails($d);
        if(!empty($d['id'])){
            $savedQuery=$pdo->prepare('SELECT inspection_type,entity_id,spec_profile_id FROM t_qc_inspection WHERE id=?');
            $savedQuery->execute([(int)$d['id']]);$savedInspection=$savedQuery->fetch();
            if(!$savedInspection||$savedInspection['inspection_type']!=='INCOMING')json_response(['success'=>false,'message'=>'Incoming record not found.'],404);
            if((int)$savedInspection['entity_id']===$entityId)$profile=get_spec_profile_by_id($pdo,(int)$savedInspection['spec_profile_id']);
        }
        if(!$profile)json_response(['success'=>false,'message'=>'No matching supplier specification is available.'],422);
        $d['results']=IncomingForm::results($profile,$d['results']??[]);
        $d['_incoming_snapshot']=IncomingForm::snapshot($profile,$entity);
        $incomingForm=is_array($d['incoming_form']??null)?$d['incoming_form']:[];
        $d['_incoming_form']=IncomingForm::measurements($profile,$entity,$incomingForm);
        $decisions=array_values(array_intersect($d['_incoming_form']['disposition_checks'],['ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC']));
        $d['proposed_disposition']=$decisions[0]??'';
        $d['_incoming_form']['signoff']=IncomingForm::signoff($incomingForm,$u,incoming_verifier_options($pdo));
    }else{unset($d['_incoming_snapshot'],$d['_incoming_form']);}
    if($type==='INPROCESS'&&$action==='save'&&!empty($d['spec_profile_id'])){
        $selectedProfile=get_spec_profile_by_id($pdo,(int)$d['spec_profile_id']);
        if(!$selectedProfile||$selectedProfile['entity_type']!==$entityType||(int)$selectedProfile['entity_id']!==$entityId||strtoupper($selectedProfile['spec_type'])!==$specType||!qc_factory_scope_match($selectedProfile['factory_scope']??'',$plant)){
            json_response(['success'=>false,'message'=>'The selected line specification is no longer applicable. Reload Inprocess.'],422);
        }
        $profile=$selectedProfile;
    }
    $proposed=strtoupper(trim((string)($d['proposed_disposition']??'')));if(!$profile)json_response(['success'=>false,'message'=>'No matching active specification found.'],422);
    unset($d['_line_preset_snapshot']);
    if($type==='INPROCESS'&&$action==='save'&&($d['source']??'')==='INPROCESS_GRID'){
        qc_tracking_storage($pdo);
        $d['batch_no']=trim((string)($d['batch_no']??''));
        if(qc_text_length($d['batch_no'])>100)throw new InvalidArgumentException('Batch number must be 100 characters or fewer.');
        $date=substr(trim((string)($d['inspection_at']??'')),0,10);
        $samplingTime=substr(trim((string)($d['inspection_at']??'')),11);
        try{$preset=LinePreset::forShift($pdo,$plant,$date,$samplingTime);}catch(InvalidArgumentException $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}
        $requested=array_map(fn($r)=>(int)($r['rule_id']??0),$d['results']??[]);
        $requestedRules=array_values(array_filter($profile['rules'],fn($rule)=>in_array((int)$rule['id'],$requested,true)));
        $entry=LinePreset::gridEntry($preset['rows'],qc_master_data()['locations'],$entityId,trim((string)($d['sampling_location']??'')),$requestedRules);
        if(!$requestedRules||count($requestedRules)!==count($requested)||!$entry){
            json_response(['success'=>false,'message'=>'The tank setup for this shift has changed. Reload Inprocess before saving.'],409);
        }
        $d['_line_preset_snapshot']=['plant'=>$plant,'date'=>$date,'sampling_time'=>$samplingTime,'rows'=>$preset['snapshot_rows'],'entry'=>$entry];
    }
    if($type==='INCOMING'&&$proposed!==''&&!in_array($proposed,['PASS','FAIL','ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC'],true))json_response(['success'=>false,'message'=>'Invalid proposed disposition.'],422);
    $id=(int)($d['id']??0);$attempt=1;$old=null;
    if($type==='INPROCESS'&&($d['source']??'')!=='INPROCESS_GRID')throw new InvalidArgumentException('Open the Inprocess form to save readings.');
    $pdo->beginTransaction();$runId=null;
    if($type==='INPROCESS'){
        $runSaved=InprocessBatch::save($pdo,['run_id'=>$d['run_id']??0,'plant'=>$plant,'date'=>$date,'sampling_time'=>$samplingTime,'location_id'=>$entry['location_id'],'product_id'=>$entityId,'batch_no'=>$d['batch_no'],'preset'=>$entry],$u,false);
        $runId=$runSaved['run_id'];$run=InprocessRuns::get($pdo,$runId,true);
        InprocessRuns::assertWritable($pdo,$run,$requestedRules,$d);
    }
    if($id){
        $st=$pdo->prepare('SELECT * FROM t_qc_inspection WHERE id=? FOR UPDATE');
        $st->execute([$id]);$old=$st->fetch();
        if(!$old){$pdo->rollBack();json_response(['success'=>false,'message'=>'Inspection not found.'],404);}
        if($old['inspection_type']!==$type){$pdo->rollBack();json_response(['success'=>false,'message'=>'Open this record in its original module.'],409);}
        if($type==='INPROCESS'&&(int)$old['inprocess_batch_id']!==$runId)throw new InvalidArgumentException('This reading belongs to a different test. Reload Inprocess.');
        $editableIncoming=$type==='INCOMING'&&$old['status']!=='CANCELLED';
        if($old['status']!=='DRAFT'&&!$editableIncoming){$pdo->rollBack();json_response(['success'=>false,'message'=>'This record is read only.'],409);}
    }
    $results=build_results($pdo,$profile,$d,$attempt,$u,true);$overall=$type==='INPROCESS'?InprocessGrid::overallDisposition($results):overall_from_results($results);
    if(!$id){
        $no=inspection_number($type);$chem=$chemical;
        $ins=$pdo->prepare('INSERT INTO t_qc_inspection (inspection_no,inspection_type,spec_type,entity_type,entity_id,spec_profile_id,chemical_name,batch_no,plant,factory,supplier,sampling_location,tank_no,coa_reference,manufacturing_date,received_date,expiry_date,inspection_at,packaging_condition,status,overall_disposition,proposed_disposition,remarks,created_by_badge,created_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$no,$type,$specType,$entityType,$entityId,(int)$profile['id'],$chem,trim((string)($d['batch_no']??'')),$plant,$factory,$supplier,trim((string)($d['sampling_location']??'')),trim((string)($d['tank_no']??'')),trim((string)($d['coa_reference']??'')),valid_date_or_null($d['manufacturing_date']??''),valid_date_or_null($d['received_date']??''),valid_date_or_null($d['expiry_date']??''),($d['inspection_at']??date('Y-m-d H:i:s')),trim((string)($d['packaging_condition']??'')),'DRAFT',$overall,$proposed,trim((string)($d['remarks']??'')),$u['badge'],$u['name']]);$id=(int)$pdo->lastInsertId();
    }else{
        $up=$pdo->prepare('UPDATE t_qc_inspection SET entity_id=?,spec_profile_id=?,chemical_name=?,batch_no=?,plant=?,factory=?,supplier=?,sampling_location=?,tank_no=?,coa_reference=?,manufacturing_date=?,received_date=?,expiry_date=?,inspection_at=?,packaging_condition=?,overall_disposition=?,proposed_disposition=?,remarks=?,updated_at=NOW() WHERE id=?');
        $up->execute([$entityId,(int)$profile['id'],$chemical,trim((string)($d['batch_no']??'')),$plant,$factory,$supplier,trim((string)($d['sampling_location']??'')),trim((string)($d['tank_no']??'')),trim((string)($d['coa_reference']??'')),valid_date_or_null($d['manufacturing_date']??''),valid_date_or_null($d['received_date']??''),valid_date_or_null($d['expiry_date']??''),($d['inspection_at']??date('Y-m-d H:i:s')),trim((string)($d['packaging_condition']??'')),$overall,$proposed,trim((string)($d['remarks']??'')),$id]);
        $pdo->prepare('DELETE FROM t_qc_result WHERE inspection_id=? AND attempt_no=1')->execute([$id]);
    }
    $ri=$pdo->prepare('INSERT INTO t_qc_result (inspection_id,spec_rule_id,parameter_code,parameter_name,result_value,result_text,unit,disposition,attempt_no,raw_input_json,created_by_badge,created_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach($results as $r)$ri->execute([$id,$r['spec_rule_id'],$r['parameter_code'],$r['parameter_name'],$r['result_value'],$r['result_text'],$r['unit'],$r['disposition'],$r['attempt_no'],$r['raw_input_json'],$r['created_by_badge'],$r['created_by_name']]);
    if($type==='INPROCESS'&&$action==='save'&&($d['source']??'')==='INPROCESS_GRID'){
        $pdo->prepare('UPDATE t_qc_inspection SET inprocess_batch_id=? WHERE id=?')->execute([$runId,$id]);
    }
    $newStatus='DRAFT';
    if($type==='INCOMING'){
        $up=$pdo->prepare("UPDATE t_qc_inspection SET status='DRAFT',tested_by_badge=?,tested_by_name=?,final_disposition=NULL,verified_by_badge=NULL,verified_by_name=NULL,verified_at=NULL,submitted_at=NULL,updated_at=NOW() WHERE id=?");
        $up->execute([$u['badge'],$u['name'],$id]);
    }
    $pdo->commit();
    $first=$results[0]??null;
    $modifiedQuery=$pdo->prepare('SELECT updated_at FROM t_qc_inspection WHERE id=?');
    $modifiedQuery->execute([$id]);$modified=(string)$modifiedQuery->fetchColumn();
    json_response(['success'=>true,'id'=>$id,'run_id'=>$runId,'status'=>$newStatus,'overall_disposition'=>$overall,'result_disposition'=>$first['disposition']??$overall,'result_value'=>$first['result_value']??null,'result_text'=>$first['result_text']??null,'attempt_no'=>$attempt,'modified'=>$modified,'modified_display'=>$modified!==''?date('d/m/Y H:i',strtotime($modified)):'--','modified_by_name'=>$u['name'],'results'=>$type==='INCOMING'?$results:[]]);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('inspection POST: '.$e->getMessage());json_response(['success'=>false,'message'=>'Unable to save inspection.'],500);}


