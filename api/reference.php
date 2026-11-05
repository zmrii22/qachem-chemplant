<?php
require_once __DIR__.'/../app/master.php';
require_once __DIR__.'/../app/ReferenceStore.php';
$user=require_login();$pdo=db_connect();
if(!ReferenceStore::ready($pdo))json_response(['success'=>false,'message'=>'Run the product reference migration first.'],503);
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  if(!empty($_GET['id']))json_response(['success'=>true,'record'=>ReferenceStore::spec($pdo,(int)$_GET['id'])]);
  $products=qc_master_products();$selections=[];
  foreach($pdo->query('SELECT product_id,remark_id FROM t_qc_product_coa_remark') as $row)$selections[(int)$row['product_id']][]=(int)$row['remark_id'];
  foreach($products as &$product)$product['remark_ids']=$selections[(int)$product['id']]??[];
  unset($product);
  json_response(['success'=>true,'remarks'=>ReferenceStore::remarks($pdo),'products'=>$products,'parameters'=>ReferenceStore::parameters(),'methods'=>qc_master_methods()]);
 }
 require_level(3);verify_csrf();$data=json_input();$action=$data['action']??'';$id=(int)($data['id']??0);$pdo->beginTransaction();
 if($action==='remark_save')$id=ReferenceStore::saveRemark($pdo,$data,$user);
 elseif($action==='spec_save')$id=ReferenceStore::saveSpec($pdo,$data,$user);
 elseif($action==='remark_delete'){
  $s=$pdo->prepare('UPDATE t_qc_coa_remark SET active=0,updated_by=? WHERE id=? AND active=1');$s->execute([$user['name'],$id]);if(!$s->rowCount())throw new InvalidArgumentException('Remark not found.');
 }elseif($action==='spec_delete'){
  $s=$pdo->prepare('UPDATE t_qc_product_spec SET active=0,updated_by=? WHERE id=? AND active=1');$s->execute([$user['name'],$id]);if(!$s->rowCount())throw new InvalidArgumentException('Specification not found.');
 }else throw new InvalidArgumentException('Unknown action.');
 $pdo->commit();json_response(['success'=>true,'id'=>$id]);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();json_response(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Reference editor: '.$e->getMessage());json_response(['success'=>false,'message'=>'Unable to save reference data.'],500);}
