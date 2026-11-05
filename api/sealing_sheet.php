<?php
require_once __DIR__.'/../app/bootstrap.php';
$user=require_login();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['status'=>'error','message'=>'Use POST.'],405);
$data=json_input();$mode=(string)($data['mode']??'');
if(!in_array($mode,['range','create','update'],true)||($data['table']??'')!=='Submission')json_response(['status'=>'error','message'=>'Invalid sealing request.'],422);
if($mode!=='range')require_permission('edit');
verify_csrf();
// Keep the existing sheet service behind the same session permissions as the app.
$url=getenv('QACHEM_SEALING_URL') ?: '';
if ($url === '') json_response(['status'=>'error','message'=>'Sealing integration is not configured in this portfolio export.'],503);
try{
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_HTTPHEADER=>['Content-Type: text/plain;charset=utf-8'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45]);
    $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);$error=curl_error($curl);curl_close($curl);
    if($body===false||$status>=400)throw new RuntimeException($error?:'Sheet service returned HTTP '.$status);
    $result=json_decode($body,true);
    if(!is_array($result))throw new RuntimeException('Invalid sheet response.');
    json_response($result);
}catch(Throwable $error){
    error_log('Sealing sheet: '.$error->getMessage());
    json_response(['status'=>'error','message'=>'Unable to reach the sealing service. Try again.'],502);
}
