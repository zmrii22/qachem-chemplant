<?php
require_once __DIR__.'/app/bootstrap.php';
$u=require_permission('line_preset');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QACHEM | Line Preset</title>
  <?php include __DIR__.'/include/mainstyle.php'; ?>
  <?php include __DIR__.'/include/mainjs.php'; ?>
  <link rel="stylesheet" href="assets/inprocess.css?v=<?=filemtime(__DIR__.'/assets/inprocess.css')?>">
  <link rel="stylesheet" href="assets/line-preset.css?v=<?=filemtime(__DIR__.'/assets/line-preset.css')?>">
</head>
<body>
<?php include __DIR__.'/include/mainnavbar.php'; ?>
<main class="legacy-main">
  <div class="legacy-titlebar"><div><h1 class="legacy-title">Line Preset</h1><p class="page-subtitle">Running tanks by plant. Saved readings are kept.</p></div><a class="btn" id="openInprocess" href="Inprocess.php">Open Inprocess</a></div>
  <div class="legacy-filter">
    <label>Plant<select id="presetPlant"><option>F21RBD</option><option>F40ARP</option></select></label>
    <button class="load-grid-btn" id="loadPreset" type="button">Load Preset</button>
    <button class="btn primary" id="savePreset" type="button" disabled><i class="fi fi-rr-disk" aria-hidden="true"></i> Save Preset</button>
  </div>
  <div class="summary-toolbar"><span id="presetState" class="state-line" role="status" aria-live="polite">Select a plant.</span><span class="summary-hint">Run + ON = shown in Inprocess</span></div>
  <div class="grid-scroll preset-scroll" role="region" aria-label="Tank line presets" tabindex="0"><table id="presetTable"><thead></thead><tbody></tbody></table></div>
</main>
<?php include __DIR__.'/include/mainfooter.php'; ?>
<script src="assets/js/line-preset.js?v=<?=filemtime(__DIR__.'/assets/js/line-preset.js')?>"></script>
</body></html>
