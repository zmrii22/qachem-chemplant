<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/IncomingForm.php';
$u=require_login();$pdo=db_connect();
if(!empty($_GET['id'])){header('Location: IncomingForm.php?id='.(int)$_GET['id']);exit;}
$ready=table_exists($pdo,'t_qc_inspection');
$directory=json_decode((string)file_get_contents(__DIR__.'/data/incoming_suppliers.json'),true);
$recent=$ready?$pdo->query("SELECT i.*, (SELECT r.raw_input_json FROM t_qc_result r WHERE r.inspection_id=i.id AND r.attempt_no=1 ORDER BY r.id DESC LIMIT 1) AS form_json FROM t_qc_inspection i WHERE inspection_type='INCOMING' ORDER BY inspection_at DESC,id DESC LIMIT 100")->fetchAll():[];
$averageLabels=['TSC'=>['TSC','Total Solid Content'],'MOISTURE'=>['MC','Moisture Content'],'WET_SIEVE'=>['WSR','Wet Sieve Residue'],'DRY_SIEVE'=>['DSR','Dry Sieve Residue'],'PURITY'=>['PUR','Purity']];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Incoming Raw Material | QACHEM</title>
  <?php include __DIR__.'/include/mainstyle.php';include __DIR__.'/include/mainjs.php'; ?>
  <link rel="stylesheet" href="assets/incoming.css?v=<?=filemtime(__DIR__.'/assets/incoming.css')?>">
</head>
<body class="incoming-screen">
<?php include __DIR__.'/include/mainnavbar.php'; ?>
<main class="page incoming-page">
  <header class="page-head"><div><h1>Incoming Raw Material</h1></div><div class="actions"><button class="btn" id="supplierDirectoryBtn" type="button"><i class="fi fi-rr-book-alt" aria-hidden="true"></i> Supplier directory</button><button class="btn primary" id="newIncoming" <?=user_can('edit',$u)?'':'hidden'?> type="button" <?=!$ready?'disabled':''?>><i class="fi fi-rr-plus-small" aria-hidden="true"></i> New Incoming Form</button></div></header>
  <?php if(!$ready): ?><div class="alert warn">Run <code>php tools/migrate.php</code> to install the existing inspection tables.</div><?php else: ?>
  <section aria-labelledby="recentHeading">
    <div class="incoming-list-head"><h2 id="recentHeading">Incoming records</h2><span class="muted" id="recordCount"><?=count($recent)?> records · latest 100</span></div>
    <div class="incoming-filters">
      <label>Search<input type="search" id="recordSearch" placeholder="Material, supplier, batch or record number"></label>
      <label>Result / Disposition<select id="recordDisposition"><option value="">All results</option><?php foreach(['PASS','FAIL','HOLD','ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC','PENDING'] as $disposition): ?><option><?=e($disposition)?></option><?php endforeach; ?></select></label>
      <label>Received from<input type="date" id="receivedFrom"></label><label>To<input type="date" id="receivedTo"></label><button type="button" class="btn" id="resetFilters">Reset</button>
      <div class="incoming-result-totals" role="group" aria-label="Matching records by result" aria-live="polite"><span class="incoming-result-total is-pass">PASS <strong id="incomingPassCount">0</strong></span><span class="incoming-result-total is-fail">FAIL <strong id="incomingFailCount">0</strong></span></div>
    </div>
    <div class="incoming-table-scroll"><table class="incoming-records">
      <thead>
        <tr><th rowspan="2" scope="col">Record</th><th rowspan="2" scope="col">Received</th><th rowspan="2" scope="col">Material</th><th rowspan="2" scope="col">Supplier</th><th rowspan="2" scope="col">Batch</th><th rowspan="2" scope="col">PASS / FAIL</th><th rowspan="2" scope="col">Disposition</th><th colspan="5" scope="colgroup" class="incoming-average-group"><span class="incoming-average-heading">Average results <button class="incoming-average-info" id="incomingAverageInfo" type="button" aria-label="Average result abbreviations" aria-describedby="incomingAverageHelp" aria-expanded="false"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5"/><path d="M10 9v5M10 6h.01"/></svg></button></span></th><th rowspan="2" scope="col">Modified</th><th rowspan="2" class="incoming-open-cell" scope="col" aria-label="Open form"></th></tr>
        <tr><?php foreach($averageLabels as [$shortLabel,$fullLabel]): ?><th scope="col" class="incoming-average-subhead" title="<?=e($fullLabel)?>"><?=e($shortLabel)?></th><?php endforeach; ?></tr>
      </thead>
      <tbody id="incomingRecords"><?php foreach($recent as $record):
        $saved=json_decode($record['form_json']??'{}',true)?:[];$form=$saved['incoming_form']??[];$checks=$form['disposition_checks']??[];
        $resultStatus=array_values(array_intersect($checks,['PASS','FAIL']))[0]??(in_array($record['overall_disposition'],['PASS','FAIL'])?$record['overall_disposition']:'PENDING');
        $disp=array_values(array_intersect($checks,['ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC']))[0]??($record['proposed_disposition']?:'—');
        $averages=array_fill_keys(array_keys($averageLabels),[]);
        foreach($form['sections']??[] as $code=>$section){
          if(empty($section['enabled'])||!isset($section['average'])||!is_numeric($section['average']))continue;
          $baseCode=preg_replace('/^PURITY_\d+$/','PURITY',$code);
          if(!isset($averages[$baseCode]))continue;
          $averages[$baseCode][]=['label'=>$averageLabels[$baseCode][0].($baseCode!==$code?substr($code,7):''),'value'=>(float)$section['average']];
        }
      ?>
        <tr data-record-id="<?=e($record['id'])?>" data-disposition="<?=e($disp)?>" data-result="<?=e($resultStatus)?>" data-received="<?=e($record['received_date'])?>">
          <td><strong><?=e($record['inspection_no'])?></strong><small><?=e($record['inspection_at'])?></small></td>
          <td><?=e($record['received_date']?:'—')?></td>
          <td><?=e($record['chemical_name'])?></td>
          <td><?=e($record['supplier'])?></td><td><?=e($record['batch_no'])?></td>
          <td><span class="badge <?=e(strtolower($resultStatus))?>"><?=e($resultStatus)?></span></td><td><span class="badge <?=e(strtolower(str_replace(' ','-',$disp)))?>"><?=e($disp)?></span></td>
          <?php foreach($averages as $code=>$values): ?><td class="incoming-average-cell<?=!$values?' is-empty':''?>" data-average-code="<?=e($code)?>"><?php foreach($values as $average): ?><span><?php if(count($values)>1): ?><small><?=e($average['label'])?></small><?php endif; ?><?=e(number_format($average['value'],2))?> %</span><?php endforeach;if(!$values): ?>—<?php endif; ?></td><?php endforeach; ?>
          <td class="incoming-modified"><small><?=e($record['tested_by_name']?:$record['created_by_name'])?></small><?=e($record['updated_at'])?></td>
          <td class="incoming-open-cell"><button class="incoming-open-button" type="button" data-open-incoming="<?=e($record['id'])?>" title="Open form" aria-label="Open form for <?=e($record['chemical_name'])?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6m0-6L10 14M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/></svg></button></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table><div class="empty" id="noIncoming" <?=count($recent)?'hidden':''?>>No incoming records found.</div></div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__.'/include/mainfooter.php'; ?>
<div id="incomingAverageHelp" class="incoming-average-tooltip" role="tooltip" hidden><strong>Average results</strong><dl><?php foreach($averageLabels as [$shortLabel,$fullLabel]): ?><div><dt><?=e($shortLabel)?></dt><dd><?=e($fullLabel)?></dd></div><?php endforeach; ?></dl></div>
<dialog id="incomingChooser" class="incoming-dialog incoming-chooser" aria-labelledby="incomingChooserTitle">
  <header class="incoming-dialog-head"><div><h2 id="incomingChooserTitle">New Incoming Form</h2><p class="muted">Choose the raw material form.</p></div><button type="button" class="btn small" id="closeIncomingChooser">Close</button></header>
  <div class="incoming-dialog-body incoming-form-choices">
    <a class="incoming-form-choice" href="IncomingLiquid.php"><span class="incoming-choice-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C9 7 5 11 5 15a7 7 0 0 0 14 0c0-4-4-8-7-12Z"/></svg></span><span><strong>Liquid</strong><small>Liquid raw material inspection</small></span><span class="incoming-choice-arrow" aria-hidden="true">→</span></a>
    <a class="incoming-form-choice" href="IncomingPowder.php"><span class="incoming-choice-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 19 8-13 8 13H4Z"/><path d="M9 15h.01M12 12h.01M15 16h.01"/></svg></span><span><strong>Powder</strong><small>Powder raw material inspection</small></span><span class="incoming-choice-arrow" aria-hidden="true">→</span></a>
  </div>
</dialog>
<dialog id="supplierDirectory" class="incoming-dialog incoming-directory" aria-labelledby="supplierDirectoryTitle"><header class="incoming-dialog-head"><div><h2 id="supplierDirectoryTitle">Supplier directory</h2><p class="muted">Workbook Sheet2 · supplier reference only; test limits come from Sheet1.</p></div><button class="btn small" id="closeSupplierDirectory" type="button">Close</button></header><div class="incoming-dialog-body"><label>Search directory<input type="search" id="directorySearch" placeholder="Chemical or supplier"></label><div id="directoryEntries"></div></div></dialog>
<script>window.QACHEM_INCOMING=<?=json_encode(['directory'=>$directory['entries']??[]],JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/js/incoming-list.js?v=<?=filemtime(__DIR__.'/assets/js/incoming-list.js')?>"></script>
</body></html>
