<?php
require_once __DIR__.'/../app/InprocessGrid.php';
require_once __DIR__.'/../app/LinePreset.php';
require_once __DIR__.'/../app/InprocessBatch.php';
$u=require_login();
try{
    $pdo=db_connect();qc_tracking_storage($pdo);
    $plant=strtoupper(trim((string)($_GET['plant']??'F21RBD')));
    if(!in_array($plant,['F21RBD','F40ARP'],true))json_response(['success'=>false,'message'=>'Invalid plant.'],400);
    $tab=strtoupper(trim((string)($_GET['tab']??'PH')));
    $valid=array_column(InprocessGrid::tabs(),'key');
    if($tab!=='ALL'&&!in_array($tab,$valid,true))json_response(['success'=>false,'message'=>'Invalid parameter tab.'],400);
    $date=trim((string)($_GET['date']??''));
    $samplingTime=trim((string)($_GET['sampling_time']??''));
    $preset=LinePreset::forShift($pdo,$plant,$date,$samplingTime);
    $current=LinePreset::rows($plant);
    // Initial entries are shared across operators; preserve separate measured runs and follow-up history.
    $editorBadge=user_can('rawform',$u)?(string)$u['badge']:'';
    $data=InprocessGrid::dbPayload($pdo,$plant,'ALL',$date,$samplingTime,'');
    $data=LinePreset::apply($data,$preset['rows']);
    $runCache=[];
    foreach($data['parameter_rows'] as $parameter=>&$rows){
        InprocessBatch::attach($pdo,$rows,$plant,$date,$samplingTime,'',$runCache,$editorBadge);
        $rows=LinePreset::filterEmptyEntries($rows,$current,$parameter);
        if(!user_can('rawform',$u))$rows=InprocessGrid::resultsOnly($rows);
    }
    unset($rows);
    $data['preset_saved']=$preset['saved'];$data['preset_source']=$preset['source'];
    $data['current_running_count']=count(array_unique(array_column(array_filter($current,fn($row)=>$row['status']==='RUN'),'location_id')));
    $data['running_count']=LinePreset::runningTankCount($data['parameter_rows']);
    foreach($data['tabs'] as &$info)$info['count']=count($data['parameter_rows'][InprocessGrid::normalizeTab($info['key'])]??[]);
    unset($info);
    if($tab!=='ALL')$data['rows']=$data['parameter_rows'][InprocessGrid::normalizeTab($tab)]??[];
    $data['active']=$tab;
    json_response(['success'=>true]+$data);
}catch(InvalidArgumentException $e){json_response(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){
    error_log('inprocess grid: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'Unable to load in-process grid.'],500);
}
