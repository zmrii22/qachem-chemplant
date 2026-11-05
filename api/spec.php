<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/spec.php';
require_login();
try {
    $pdo=db_connect();
    $entityType=strtoupper(trim($_GET['entity_type']??''));
    $entityId=(int)($_GET['entity_id']??0);
    $specType=strtoupper(trim($_GET['spec_type']??''));
    $factory=trim($_GET['factory']??'');
    $supplier=trim($_GET['supplier']??'');
    if(!in_array($entityType,['PRODUCT','RAW_MATERIAL'],true)||$entityId<1||!in_array($specType,['IQS','QS','INCOMING'],true)) json_response(['success'=>false,'message'=>'Invalid specification request.'],400);
    $profile=get_spec_profile($pdo,$entityType,$entityId,$specType,$factory,$supplier);
    if(!empty($_GET['profile_id'])){
        $selected=get_spec_profile_by_id($pdo,(int)$_GET['profile_id']);
        if(!$selected||$selected['entity_type']!==$entityType||(int)$selected['entity_id']!==$entityId||$selected['spec_type']!==$specType||!qc_supplier_scope_match($selected['supplier_scope']??'',$supplier))json_response(['success'=>false,'message'=>'The saved specification does not match this material.'],422);
        $profile=$selected;
    }
    if(!$profile) json_response(['success'=>false,'message'=>'No active specification matches this selection.'],404);
    $methods=[];
    foreach(qc_master_methods() as $m) $methods[(int)$m['method_key']]=$m;
    foreach($profile['rules'] as &$r){
        $r['display']=rule_display($r);
        $r['method']=$r['method_key']!==null ? ($methods[(int)$r['method_key']]??null) : null;
    }
    json_response(['success'=>true,'profile'=>$profile]);
} catch(Throwable $e){ error_log('spec api: '.$e->getMessage()); json_response(['success'=>false,'message'=>'Unable to load specification.'],500); }
