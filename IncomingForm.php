<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/master.php';
require_once __DIR__.'/include/incoming_fields.php';
require_once __DIR__.'/include/incoming_signoff.php';
$u=require_login();
if(empty($_GET['id']))require_permission('edit');
$verifiers=incoming_verifier_options(db_connect());
$type=$incomingFormType??strtoupper((string)($_GET['type']??'LIQUID'));
if(!empty($_GET['id'])){
    $statement=db_connect()->prepare("SELECT entity_id FROM t_qc_inspection WHERE id=? AND inspection_type='INCOMING'");
    $statement->execute([(int)$_GET['id']]);$entity=$statement->fetchColumn();
    $savedMaterial=$entity?qc_master_raw_material_by_id((int)$entity):null;
    if(!$savedMaterial){http_response_code(404);exit('Incoming form not found.');}
    $type=strtoupper($savedMaterial['material_type']);
}
if(!in_array($type,['LIQUID','POWDER'],true)){http_response_code(400);exit('Choose Liquid or Powder.');}
$materials=qc_master_active('raw_materials');
usort($materials,fn($a,$b)=>strnatcasecmp($a['material_name'].$a['supplier'],$b['material_name'].$b['supplier']));
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Incoming Raw Material (<?=e(ucfirst(strtolower($type)))?>) Inspection Form</title>
<link rel="stylesheet" href="assets/incoming-forms.css?v=<?=filemtime(__DIR__.'/assets/incoming-forms.css')?>">
<link rel="stylesheet" href="assets/incoming-paper-fixes.css?v=<?=filemtime(__DIR__.'/assets/incoming-paper-fixes.css')?>">
<link rel="stylesheet" href="assets/incoming-form-page.css?v=<?=filemtime(__DIR__.'/assets/incoming-form-page.css')?>">
<style>@page{size:Letter <?=$type==='POWDER'?'landscape':'portrait'?>;margin:0}</style>
</head><body data-form-id="incoming-<?=e(strtolower($type))?>" class="incoming-form-page">
<main id="incomingDialog">
<form id="incomingForm">
<select id="incomingFormType" hidden><option value="LIQUID" <?=$type==='LIQUID'?'selected':''?>>Liquid</option><option value="POWDER" <?=$type==='POWDER'?'selected':''?>>Powder</option></select>
<select id="incomingProposed" hidden><option value="">Follow calculated result</option><option>PASS</option><option>FAIL</option><option>ACCEPT</option><option>ACCEPT WITH LOC</option><option>REJECT WITH LOC</option></select>
<div class="incoming-form-layout">
  <div class="incoming-document-column"><div id="incomingPaper"></div></div>
  <aside class="incoming-side-panel screen-only" aria-label="Form actions and supplier reference">
    <section class="incoming-actions" aria-label="Form actions">
      <div class="incoming-action-heading"><a href="Incoming.php" id="closeIncoming">← Forms</a><span><?=e(ucfirst(strtolower($type)))?> inspection</span></div>
      <div class="incoming-record-heading"><span id="incomingRecordNo">NEW RECORD</span></div>
      <div class="incoming-action-buttons">
        <button type="button" id="saveIncoming" class="primary" <?=user_can('edit',$u)?'':'hidden'?>>Save Form</button>
        <button type="button" id="printIncoming">Print / Save PDF</button>
      </div>
      <p id="incomingState" role="status" aria-live="polite">Ready</p>
      <div class="incoming-secondary-actions" <?=user_can('edit',$u)?'':'hidden'?>><button type="button" id="clearIncoming">Clear form</button></div>
    </section>
    <section class="incoming-reference" aria-labelledby="incomingReferenceTitle">
      <header><div class="incoming-reference-heading"><h3 id="incomingReferenceTitle">Supplier reference</h3><span id="overallPreview" class="badge pending">PENDING</span></div><p id="incomingReferenceSelection">Select a chemical and supplier to see its reference.</p><button type="button" id="toggleIncomingReference" aria-expanded="true" aria-controls="incomingReferenceContent">Hide reference</button></header>
      <div id="incomingReferenceContent">
      <div id="materialDetails" class="incoming-material-details"></div>
      <div id="incomingDocumentDetails"></div><p id="incomingSpecSource" class="incoming-note muted"></p>
      </div>
    </section>
  </aside>
</div>
</form>
</main>
<dialog id="incomingCalc" class="screen-only incoming-calculator"><header><h2 id="incomingCalcTitle">Calculator</h2><button id="closeIncomingCalc" type="button">Close</button></header><p id="incomingCalcMethod"></p><div id="incomingCalcFormula"></div><div id="incomingCalcInputs"></div><p id="incomingCalcState" role="status"></p><button id="applyIncomingCalc" type="button">Calculate &amp; apply</button></dialog>
<template id="incomingLiquidTemplate"><?php include __DIR__.'/include/incoming_liquid.php'; ?></template>
<template id="incomingPowderTemplate"><?php include __DIR__.'/include/incoming_powder.php'; ?></template>
<section id="incomingPrint" aria-label="Incoming inspection print form"></section>
<script>window.QACHEM_CSRF=<?=json_encode(csrf_token())?>;window.QACHEM_INCOMING=<?=json_encode(['canEdit'=>user_can('edit',$u),'formPage'=>true,'formType'=>$type,'materials'=>$materials,'verifiers'=>$verifiers,'user'=>$u['name'],'today'=>date('Y-m-d'),'now'=>date('Y-m-d\TH:i')],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/js/feedback.js?v=<?=filemtime(__DIR__.'/assets/js/feedback.js')?>"></script>
<script src="assets/js/incoming-templates.js?v=<?=filemtime(__DIR__.'/assets/js/incoming-templates.js')?>"></script>
<script src="assets/js/incoming.js?v=<?=filemtime(__DIR__.'/assets/js/incoming.js')?>"></script>
</body></html>
