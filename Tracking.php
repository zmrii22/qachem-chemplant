<?php
require_once __DIR__.'/app/Tracking.php';
$user=require_login();qc_tracking_storage(db_connect());$reference=Tracking::reference();
$factoryChoices=array_values(array_filter($reference['factories'],fn($factory)=>!in_array($factory['code'],['F9','F19','F20','F5C','F2','F18','F18G','QA','F02B','F2B'],true)));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tracking | QACHEM</title><?php include __DIR__.'/include/mainstyle.php';include __DIR__.'/include/mainjs.php';?><link rel="stylesheet" href="assets/tracking.css?v=<?=filemtime(__DIR__.'/assets/tracking.css')?>"></head><body>
<?php include __DIR__.'/include/mainnavbar.php';?>
<main class="page tracking-page">
  <div class="page-head"><h1>Tracking</h1><div class="actions"><button type="button" id="exportTracking" class="btn" title="Export all records">Export Excel</button><button type="button" id="newTracking" <?=user_can('tracking_plan',$user)?'':'hidden'?> class="btn primary">New Tracking</button></div></div>
  <form id="trackingFilters" class="tracking-filters"><label>From<input type="date" name="from" value="<?=date('Y-m-d',strtotime('-7 days'))?>"></label><label>To<input type="date" name="to" value="<?=date('Y-m-d')?>"></label><label>Plant<select name="plant"><option value="">All plants</option><option>F21RBD</option><option>F40ARP</option></select></label><label class="tracking-search">Search<input type="search" name="q" placeholder="Chemical, batch, factory or PO"></label><button class="btn" type="submit">Load</button></form>
  <p id="trackingState" role="status" aria-live="polite" class="muted">Loading…</p>
  <div class="table-wrap tracking-table-wrap"><table class="data-table tracking-table"><thead><tr><th aria-label="Open tracking"></th><th>Manufacturing Date</th><th>Chemical</th><th>Batch</th><th>Tank / Drum</th><th>Factory</th><th>PO</th><th>Remark</th><th>Testing Date</th><th>Status COA</th><th>Upload Date</th><th>Last Modified</th></tr></thead><tbody id="trackingRows"></tbody></table></div>
</main>
<?php include __DIR__.'/include/mainfooter.php';?>
<dialog id="trackingDialog" class="tracking-dialog">
  <form id="trackingForm"><header class="tracking-dialog-head"><div><h2 id="trackingFormTitle">New Tracking</h2><p id="trackingNumber" class="muted"></p></div><button type="button" class="btn small" id="closeTracking">Close</button></header>
  <div class="tracking-dialog-body"><div class="tracking-entry">
  <section class="tracking-source-picker tracking-fields" aria-label="Find saved Inprocess batch">
    <label class="full">Batch *<input id="sourceSearch" type="search" required maxlength="100" placeholder="Existing or planned batch" autocomplete="off" aria-controls="sourceKey" aria-describedby="sourceSearchState"></label>
    <label class="full">Inprocess run<select id="sourceKey"><option value="">Link later</option></select></label>
    <p id="sourceSearchState" class="muted full" role="status" aria-live="polite"></p>
    <p id="sourceInfo" class="muted full"></p>
  </section>

  <section class="tracking-manual-panel" aria-labelledby="trackingManualTitle">
    <div class="tracking-section-title"><h3 id="trackingManualTitle">Enter tracking details</h3><p>Preplan: Batch and Manufacturing Date are required. Complete the remaining details for COA.</p></div>
    <div class="tracking-fields">
    <label>Manufacturing Date *<input id="manufacturingDate" type="date" required></label>
    <label>Factory<select id="trackingFactory"><option value="">Select destination</option><?php foreach($factoryChoices as $factory):?><option value="<?=e($factory['code'])?>"><?=e($factory['code'])?> — <?=e($factory['name'])?></option><?php endforeach;?></select></label>
    <label>Specification<select id="trackingCoaSpec" disabled><option value="">Select specification</option></select></label>
    <label>Tote Tank / Drum No.<input id="packagingNo" maxlength="255" placeholder="e.g. 1,2,3 or 1 TO 4"></label><label>Customer PO No.<input id="trackingPO" maxlength="100" value="N/A"></label>
    <label>COA signatory<select id="trackingSignatory"><option value="">Select signatory</option><?php foreach($reference['verifiers'] as $name):?><option><?=e($name)?></option><?php endforeach;?></select></label>
    <label>Upload Date<input id="trackingUploadDate" type="date" min="<?=date('Y-m-d')?>" disabled aria-describedby="trackingUploadHelp"><small id="trackingUploadHelp">Sent date. Generate COA first; today onward.</small></label>
    <label>Tracking Remark (optional)<input id="trackingRemark" type="text" placeholder="Enter remark"></label>
    </div>
  </section>
  <section class="tracking-system-panel" aria-labelledby="trackingSystemTitle">
    <div class="tracking-section-title"><h3 id="trackingSystemTitle"><svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2"/><path d="M6.5 8V6a3.5 3.5 0 0 1 7 0v2"/></svg>System filled</h3></div>
    <div class="tracking-fields">
      <label>Chemical<input id="trackingChemical" readonly placeholder="Select a batch"></label>
      <label>Batch number<input id="trackingBatch" readonly placeholder="Select a batch"></label>
      <label>Plant / Inprocess tank<input id="trackingSourceContext" readonly placeholder="From selected batch"></label>
      <label>Testing Date<input id="testingDate" type="date" readonly></label>
      <label>Expiry Date<input id="trackingExpiry" type="date" readonly></label>
      <label >Customer / Company<input id="trackingCustomer" readonly placeholder="From selected factory"></label>
      <label>Inspected by<input id="trackingInspector" readonly value="<?=e($user['name'])?>"></label>

    </div>
  </section></div><aside class="tracking-reference"><h3>Results &amp; Specifications</h3><p id="trackingPreviewState" class="muted">Select a run and factory.</p><div id="trackingPreview"></div><div class="tracking-automatic-remarks tracking-reference-remarks"><span id="trackingCoaRemarksLabel" class="tracking-auto-label">COA Remarks</span><div id="trackingCoaRemarks" class="tracking-system-remarks" role="region" aria-labelledby="trackingCoaRemarksLabel" aria-live="polite"><p>Link an Inprocess run to load product remarks.</p></div><small>From Product Specification</small></div></aside></div>
  <footer class="tracking-dialog-actions"><p id="trackingFormState" role="status" aria-live="polite"></p><div class="actions"><button class="btn" type="button" id="saveTracking" <?=user_can('tracking_plan',$user)?'':'hidden'?>>Save</button><button class="btn primary" type="button" id="generateTracking" <?=user_can('generate_coa',$user)?'':'hidden'?>>Save &amp; Generate COA</button></div></footer>
  </form>
</dialog>
<script>window.QACHEM_TRACKING=<?=json_encode(['canPlan'=>user_can('tracking_plan',$user),'canEdit'=>user_can('generate_coa',$user),'reference'=>$reference,'user'=>$user,'today'=>date('Y-m-d'),'inspectionId'=>(int)($_GET['inspection_id']??0),'id'=>(int)($_GET['id']??0)],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/js/tracking.js?v=<?=filemtime(__DIR__.'/assets/js/tracking.js')?>"></script>
</body></html>
