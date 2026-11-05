<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/spec.php';
require_login();

if($_SERVER['REQUEST_METHOD'] !== 'GET'){
    json_response(['success'=>false,'message'=>'Specifications are stored in data/qachem_master.json. Database spec editing is disabled in the minimal-table build.'],410);
}

try{
    $id=(int)($_GET['id']??0);
    $profile=qc_master_profile_by_id($id);
    if(!$profile)json_response(['success'=>false,'message'=>'Specification profile not found.'],404);
    $entity=qc_master_profile_entity($profile);
    $profile+=$entity;
    $profile['entity_name']=$entity['product_name']?:$entity['material_name'];
    $profile['entity_ref']=$entity['batch_code']?:$entity['supplier'];
    json_response(['success'=>true,'profile'=>$profile]);
}catch(Throwable $e){
    error_log($e->getMessage());
    json_response(['success'=>false,'message'=>'Unable to load profile.'],500);
}
