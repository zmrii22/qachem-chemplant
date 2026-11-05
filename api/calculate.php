<?php
require_once __DIR__.'/../app/bootstrap.php';
require_once __DIR__.'/../app/Calculation.php';
require_login(); verify_csrf();
try{
    $d=json_input();
    $key=(int)($d['method_key']??0);
    $inputs=is_array($d['inputs']??null)?$d['inputs']:[];
    $out=qc_calculate($key,$inputs);
    foreach(['intermediate','result'] as $k) if(isset($out[$k]) && is_float($out[$k])) $out[$k]=round($out[$k],6);
    json_response(['success'=>true,'calculation'=>$out]);
}catch(InvalidArgumentException $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log('calc api: '.$e->getMessage());json_response(['success'=>false,'message'=>'Calculation failed.'],500);}
