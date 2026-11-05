<?php
require_once __DIR__.'/app/Tracking.php';
require_login();$pdo=db_connect();qc_tracking_storage($pdo);
try{$record=Tracking::get($pdo,(int)($_GET['tracking_id']??0));}
catch(InvalidArgumentException $error){http_response_code(404);exit('Tracking record not found.');}
header('Cache-Control: private, no-store');
try{
    if(empty($record['coa_generated_at']))throw new InvalidArgumentException('Generate the COA from Tracking first.');
    $document=Tracking::document($pdo,$record);
    if($document['problems'])throw new InvalidArgumentException(implode(' ',$document['problems']));
}catch(InvalidArgumentException $error){
    http_response_code(422);
    exit('<p>'.e($error->getMessage()).'</p><a href="Tracking.php?id='.(int)$record['id'].'">Back to Tracking</a>');
}
function coa_form_date(?string $date): string {return $date?date('d.m.Y',strtotime($date)):'';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>COA · <?=e($record['batch_no'])?></title><link rel="stylesheet" href="assets/coa-form.css?v=<?=filemtime(__DIR__.'/assets/coa-form.css')?>"></head><body>
<div class="coa-form-actions"><a href="Tracking.php?id=<?=e($record['id'])?>">← Tracking</a><span><?=e($document['batch'])?></span><button type="button" onclick="window.print()">Print / Save PDF</button></div>
<main class="coa-paper-wrap"><?php include __DIR__.'/include/coa_document.php';?></main>
</body></html>
