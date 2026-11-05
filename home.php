<?php
require_once __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/LinePreset.php';
$u=require_login();
$pdo=db_connect();
$ready=table_exists($pdo,'t_qc_inspection');
$today=date('Y-m-d');
$stats=['incoming'=>0,'inprocess'=>0,'tracking_drafts'=>null,'coa_unsent'=>null];
if($ready){
  $incomingQuery=$pdo->prepare("SELECT COUNT(*) FROM t_qc_inspection WHERE inspection_type='INCOMING' AND status<>'CANCELLED' AND inspection_at>=? AND inspection_at<?");
  $incomingQuery->execute([$today.' 00:00:00',date('Y-m-d',strtotime('tomorrow')).' 00:00:00']);
  $stats['incoming']=(int)$incomingQuery->fetchColumn();
  foreach(['F21RBD','F40ARP'] as $plant){
    $day=LinePreset::forShift($pdo,$plant,$today,'08:00:00');
    $night=LinePreset::forShift($pdo,$plant,$today,'20:00:00');
    $rows=LinePreset::withCurrentRunning($day['rows'],$night['rows']);
    $grid=LinePreset::apply(InprocessGrid::dbPayload($pdo,$plant,'ALL'),$rows);
    $stats['inprocess']+=$grid['running_count'];
  }
  if(table_exists($pdo,'t_qc_tracking')){
    $tracking=$pdo->query('SELECT COUNT(CASE WHEN coa_generated_at IS NULL THEN 1 END) AS drafts, COUNT(CASE WHEN coa_generated_at IS NOT NULL AND upload_date IS NULL THEN 1 END) AS unsent FROM t_qc_tracking')->fetch();
    $stats['tracking_drafts']=(int)$tracking['drafts'];
    $stats['coa_unsent']=(int)$tracking['unsent'];
  }
}
$name=trim((string)($u['name']??$u['badge']??''));
$role=access_label((int)$u['level']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard | QACHEM</title>
  <?php include __DIR__.'/include/mainstyle.php'; ?>
  <?php include __DIR__.'/include/mainjs.php'; ?>
  <link rel="stylesheet" href="assets/dashboard.css?v=<?=filemtime(__DIR__.'/assets/dashboard.css')?>">
</head>
<body>
<?php include __DIR__.'/include/mainnavbar.php'; ?>
<main class="page dashboard-page">
  <section class="dashboard-welcome" aria-labelledby="dashboardWelcomeTitle">
    <div class="dashboard-welcome-art" aria-hidden="true">
      <img src="assets/images/welcome-cat.png?v=<?=filemtime(__DIR__.'/assets/images/welcome-cat.png')?>" alt="" width="280" height="240">
      <div class="dashboard-welcome-animation"></div>
    </div>
    <div class="dashboard-welcome-copy">
      <p class="eyebrow"><i class="fi fi-rr-sun" aria-hidden="true"></i> A fresh start</p>
      <h1 id="dashboardWelcomeTitle">Welcome back,<span class="dashboard-welcome-name"><?=e($name)?> (<?=e($role)?>)</span></h1>
      <p class="dashboard-welcome-message">Here's to a good day and great work.</p>
    </div>
  </section>

  <?php if($ready): ?>
    <section class="dashboard-overview" aria-labelledby="overviewTitle">
      <header class="dashboard-overview-head"><h2 id="overviewTitle">Overview</h2><time datetime="<?=e($today)?>"><?=e(date('d M Y'))?></time></header>
      <div class="dashboard-stats">
        <a href="Incoming.php" class="dashboard-metric metric-incoming" aria-labelledby="incomingMetricTitle">
          <div class="metric-top"><span class="metric-icon"><i class="fi fi-rr-inbox-in" aria-hidden="true"></i></span><span class="metric-period">Today</span></div>
          <h3 id="incomingMetricTitle">Incoming forms</h3>
          <strong class="metric-value" data-metric="incoming"><?=e(number_format($stats['incoming']))?></strong>
          <p>By inspection date</p>
        </a>
        <a href="Inprocess.php" class="dashboard-metric metric-inprocess" aria-labelledby="inprocessMetricTitle" title="Today's saved Day/Night setups and current running preset. Each tank is counted once per plant.">
          <div class="metric-top"><span class="metric-icon"><i class="fi fi-rr-workflow" aria-hidden="true"></i></span><span class="metric-period">Today</span></div>
          <h3 id="inprocessMetricTitle">Running tanks</h3>
          <strong class="metric-value" data-metric="inprocess"><?=e(number_format($stats['inprocess']))?></strong>
          <p>Day / Night · Both plants</p>
        </a>
        <a href="Tracking.php" class="dashboard-metric metric-tracking" aria-labelledby="trackingMetricTitle">
          <div class="metric-top"><span class="metric-icon"><i class="fi fi-rr-document" aria-hidden="true"></i></span><span class="metric-period">All dates</span></div>
          <h3 id="trackingMetricTitle">Awaiting COA</h3>
          <strong class="metric-value" data-metric="tracking_drafts"><?=$stats['tracking_drafts']===null?'—':e(number_format($stats['tracking_drafts']))?></strong>
          <p><?=$stats['tracking_drafts']===null?'Tracking unavailable':'Tracking without a generated COA'?></p>
        </a>
        <a href="Tracking.php" class="dashboard-metric metric-delivery" aria-labelledby="deliveryMetricTitle">
          <div class="metric-top"><span class="metric-icon"><i class="fi fi-rr-box" aria-hidden="true"></i></span><span class="metric-period">All dates</span></div>
          <h3 id="deliveryMetricTitle">COA not sent</h3>
          <strong class="metric-value" data-metric="coa_unsent"><?=$stats['coa_unsent']===null?'—':e(number_format($stats['coa_unsent']))?></strong>
          <p><?=$stats['coa_unsent']===null?'Tracking unavailable':'Generated · Upload Date not set'?></p>
        </a>
      </div>
    </section>
  <?php endif; ?>

  <?php if(!$ready): ?>
    <div class="alert warn dashboard-alert">
      <strong>New QACHEM database tables are not installed yet.</strong><br>
      Configure the database environment variables, then run <code>php tools/migrate.php</code>.
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__.'/include/mainfooter.php'; ?>
<script src="assets/js/lottie-light-canvas-5.13.0.min.js?v=<?=filemtime(__DIR__.'/assets/js/lottie-light-canvas-5.13.0.min.js')?>"></script>
<script>
(function(){
  const art=document.querySelector('.dashboard-welcome-art');
  const target=document.querySelector('.dashboard-welcome-animation');
  if(!art||!target||!window.lottie)return;
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
  const animation=window.lottie.loadAnimation({
    container:target,
    renderer:'canvas',
    path:'assets/animations/welcome-cat.json?v=<?=filemtime(__DIR__.'/assets/animations/welcome-cat.json')?>',
    loop:false,
    autoplay:!reduced.matches,
    rendererSettings:{clearCanvas:true}
  });
  animation.addEventListener('DOMLoaded',function(){
    if(reduced.matches)animation.goToAndStop(0,true);
    art.classList.add('is-ready');
  });
  animation.addEventListener('data_failed',function(){art.classList.remove('is-ready');});
})();
</script>
</body>
</html>

