<?php
require_once __DIR__.'/app/bootstrap.php';
$u=require_login();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QACHEM | Inprocess</title>
  <?php include __DIR__.'/include/mainstyle.php'; ?>
  <?php include __DIR__.'/include/mainjs.php'; ?>
  <link rel="stylesheet" href="assets/inprocess.css?v=<?=filemtime(__DIR__.'/assets/inprocess.css')?>">
</head>
<body>
<?php include __DIR__.'/include/mainnavbar.php'; ?>
<main class="legacy-main" id="mainContent">
  <div class="legacy-titlebar">
    <div><h1 class="legacy-title">Inprocess</h1></div>
    <span id="loadedContext" class="context-label"></span>
  </div>
  <div class="legacy-filter">
    <label>Date<input type="date" id="inspectionDate" value="<?=e(date('Y-m-d'))?>"></label>
    <label>Sampling Time<select id="samplingTime"><option value="">-- Select --</option><option value="08:00:00">Day</option><option value="20:00:00">Night</option></select></label>
    <label>Plant<select id="plant"><option value="">-- Select --</option><option value="F21RBD">F21RBD</option><option value="F40ARP">F40ARP</option></select></label>
    <button type="button" class="load-grid-btn" id="loadGridBtn">Load</button>
  </div>
  <div class="summary-toolbar">
    <span class="state-line" id="gridState" role="status" aria-live="polite">Select a plant, then click Load.</span>
    <div class="summary-actions">
      <span class="summary-hint"><?=user_can('rawform',$u)?'Select a tank or parameter. ':'Read only. '?>Right-click results for IQS / QS.</span>
      <a href="LinePreset.php" id="linePresetShortcut" <?=user_can('line_preset',$u)?'':'hidden'?> class="btn small"><i class="fi fi-rr-settings-sliders" aria-hidden="true"></i> Line Preset</a>
    </div>
  </div>
  <div id="presetNotice" class="preset-notice" hidden><?php if(user_can('line_preset',$u)):?>Set running tanks in <a href="LinePreset.php" id="configurePreset">Line Preset</a>.<?php else:?>No running tanks. Ask an editor to configure the preset.<a id="configurePreset" hidden></a><?php endif;?></div>
  <div class="grid-scroll" role="region" aria-label="All in-process parameter results" tabindex="0">
    <table id="testing"><thead id="gridHead"></thead><tbody id="gridBody"></tbody></table>
  </div>
</main>
<?php include __DIR__.'/include/mainfooter.php'; ?>
<div class="modal-backdrop sinput-modal-backdrop" id="sInputModal" aria-hidden="true">
  <div class="modal sinput-modal" role="dialog" aria-modal="true" aria-labelledby="sInputModalTitle" aria-describedby="sInputModalMeta">
    <div class="sinput-modal-head">
      <div><h2 id="sInputModalTitle">Parameter readings</h2><p id="sInputModalMeta"></p></div>
      <button type="button" class="btn small" id="sInputClose">Close</button>
    </div>
    <div id="sInputModalBody"></div>
    <div class="sinput-modal-actions">
      <span id="sInputState" role="status" aria-live="polite"></span>
      <div class="actions"><button type="button" class="btn" id="sInputPrint" hidden>Print / Save PDF</button><button type="button" class="btn primary" id="sInputSave"><i class="fi fi-rr-disk" aria-hidden="true"></i> Save All</button></div>
    </div>
  </div>
</div>
<div id="specCompare" class="spec-compare" role="dialog" aria-label="IQS and QS result comparison" hidden tabindex="-1">
  <div class="compare-head"><strong id="compareTitle">Specification comparison</strong><button type="button" class="btn small" id="compareClose" aria-label="Close specification comparison">Close</button></div>
  <div id="compareBody" role="status" aria-live="polite"></div>
</div>
<div id="followupMenu" class="followup-menu" role="menu" aria-label="New test" hidden></div>
<dialog id="followupDialog" class="followup-drawer" aria-labelledby="followupTitle">
  <form id="followupForm" class="followup-form">
    <header class="followup-head"><div><h2 id="followupTitle">Retest</h2><span class="followup-subtitle">Inprocess</span></div><button type="button" class="btn small" id="followupClose" aria-label="Close panel"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>Close</button></header>
    <div class="followup-body">
      <div id="followupContext" class="followup-context">
        <div class="followup-context-main"><span id="followupProductIcon" class="followup-product-icon" aria-hidden="true"></span><div id="followupContextDetails" class="followup-context-details"></div></div>
        <div id="followupTypes" class="followup-types" role="group" aria-label="Test type"><span id="followupTypeInfo" class="type-info" tabindex="0" aria-label="Test icon information" aria-describedby="followupLegend">i<span class="type-legend" role="tooltip" id="followupLegend"></span></span></div>
      </div>
      <div id="followupScope" class="followup-scope" role="group" aria-label="Parameters to test"><span>Test</span><button type="button" data-followup-scope="FAILED" aria-pressed="true">Failed only</button><button type="button" data-followup-scope="ALL" aria-pressed="false">All parameters</button></div>
      <p id="followupExplanation" class="followup-note"></p>
      <div id="followupReadings" aria-live="polite"></div>
    </div>
    <footer class="followup-footer"><p id="followupError" role="status" aria-live="polite"></p><div class="actions"><button type="button" class="btn" id="followupCancel">Cancel</button><button type="submit" class="btn primary" id="followupCreate">Save</button></div></footer>
  </form>
</dialog>
<script>window.QACHEM_INPROCESS={canEdit:<?=json_encode(user_can('rawform',$u))?>,user:<?=json_encode($u['name']??'',JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,plantPreferenceKey:<?=json_encode('qachem.inprocess.plant.'.(string)$u['badge'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,timeZone:<?=json_encode(qachem_config('app')['timezone']??'Asia/Kuala_Lumpur')?>,reportCss:<?=json_encode('assets/inprocess-report.css?v='.filemtime(__DIR__.'/assets/inprocess-report.css'))?>};</script>
<script src="assets/js/inprocess-report.js?v=<?=filemtime(__DIR__.'/assets/js/inprocess-report.js')?>"></script>
<script src="assets/js/inprocess.js?v=<?=filemtime(__DIR__.'/assets/js/inprocess.js')?>"></script>
</body>
</html>
