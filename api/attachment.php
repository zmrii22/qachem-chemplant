<?php
require_once __DIR__.'/../app/bootstrap.php';$u=require_login();$pdo=db_connect();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $type=trim($_GET['entity_type']??'');$id=(int)($_GET['entity_id']??0);$st=$pdo->prepare('SELECT id,attachment_category,original_name,mime_type,file_size,uploaded_by_badge,uploaded_at FROM t_qc_attachment WHERE entity_type=? AND entity_id=? ORDER BY id');$st->execute([$type,$id]);json_response(['success'=>true,'files'=>$st->fetchAll()]);
}
require_permission('edit');verify_csrf();
$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));
if(str_contains($contentType,'application/json')){
 $d=json_input();$action=strtolower((string)($d['action']??''));
 if($action==='delete'){
  $id=(int)($d['id']??0);$st=$pdo->prepare('SELECT * FROM t_qc_attachment WHERE id=?');$st->execute([$id]);$file=$st->fetch();
  if(!$file)json_response(['success'=>false,'message'=>'Attachment not found.'],404);
  if((string)$file['uploaded_by_badge']!==(string)$u['badge']&&(int)$u['level']<2)json_response(['success'=>false,'message'=>'You can only remove your own evidence.'],403);
  $base=realpath(__DIR__.'/../uploads');$path=realpath(__DIR__.'/../uploads/'.$file['stored_name']);
  $pdo->prepare('DELETE FROM t_qc_attachment WHERE id=?')->execute([$id]);
  if($base&&$path&&strpos($path,$base)===0&&is_file($path))@unlink($path);
  json_response(['success'=>true,'id'=>$id]);
 }
}
if(empty($_FILES['file'])||!is_uploaded_file($_FILES['file']['tmp_name']))json_response(['success'=>false,'message'=>'No valid file received.'],422);
$type=strtoupper(trim($_POST['entity_type']??''));$id=(int)($_POST['entity_id']??0);
$category=strtoupper(trim((string)($_POST['category']??'GENERAL')));
if($type!=='INSPECTION'||$id<1||!in_array($category,['GENERAL','INSPECTION'],true))json_response(['success'=>false,'message'=>'Invalid inspection attachment target/category.'],422);
$check=$pdo->prepare('SELECT COUNT(*) FROM t_qc_inspection WHERE id=?');$check->execute([$id]);
if(!(int)$check->fetchColumn())json_response(['success'=>false,'message'=>'Attachment target record does not exist.'],404);
$f=$_FILES['file'];if($f['error']!==UPLOAD_ERR_OK)json_response(['success'=>false,'message'=>'Upload failed.'],422);if($f['size']>8*1024*1024)json_response(['success'=>false,'message'=>'File exceeds 8 MB limit.'],413);
$fi=new finfo(FILEINFO_MIME_TYPE);$mime=$fi->file($f['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];if(!isset($allowed[$mime]))json_response(['success'=>false,'message'=>'Only JPG, PNG, WEBP or PDF files are allowed.'],422);
$dir=__DIR__.'/../uploads/'.date('Y').'/'.date('m');if(!is_dir($dir)&&!mkdir($dir,0770,true))json_response(['success'=>false,'message'=>'Upload storage is unavailable.'],500);$name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];$path=$dir.'/'.$name;if(!move_uploaded_file($f['tmp_name'],$path))json_response(['success'=>false,'message'=>'Could not store uploaded file.'],500);
$stored=date('Y').'/'.date('m').'/'.$name;$st=$pdo->prepare('INSERT INTO t_qc_attachment (entity_type,entity_id,attachment_category,original_name,stored_name,mime_type,file_size,uploaded_by_badge,uploaded_at) VALUES (?,?,?,?,?,?,?,?,NOW())');$st->execute([$type,$id,$category,basename($f['name']),$stored,$mime,$f['size'],$u['badge']]);$aid=(int)$pdo->lastInsertId();json_response(['success'=>true,'id'=>$aid,'name'=>basename($f['name'])]);
