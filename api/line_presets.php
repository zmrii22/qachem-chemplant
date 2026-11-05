<?php
require_once __DIR__.'/../app/LinePreset.php';
$u=require_permission('line_preset');
try{
    $isPost=($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    if($isPost)verify_csrf();
    $input=$isPost?json_input():$_GET;
    $plant=strtoupper(trim((string)($input['plant']??'')));
    LinePreset::plantKey($plant);
    $catalog=LinePreset::catalog(db_connect(),$plant);
    if($isPost){
        if(!is_array($input['rows']??null))throw new InvalidArgumentException('Invalid line preset rows.');
        LinePreset::save($plant,$input['rows'],$catalog,$u);
    }
    json_response(['success'=>true,'catalog'=>$catalog,'rows'=>LinePreset::rows($plant)]);
}catch(InvalidArgumentException $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log('line presets: '.$e->getMessage());json_response(['success'=>false,'message'=>'Unable to load or save line presets. Check that the data folder is writable on the server.'],500);}
