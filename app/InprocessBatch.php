<?php
require_once __DIR__.'/TrackingStorage.php';
require_once __DIR__.'/LinePreset.php';

class InprocessBatch {
    public static function sampleType(string $value): string {
        if(!in_array($value,['NEW_SAMPLE','RETEST','REWORK'],true))throw new InvalidArgumentException('Choose New Sample, Retest or Rework.');
        return $value;
    }
    private static function roots(PDO $pdo,string $plant,string $at,string $badge='',bool $lock=false): array {
        $sql="SELECT b.*,EXISTS(SELECT 1 FROM t_qc_inspection i WHERE i.status<>'CANCELLED' AND (i.inprocess_batch_id=b.id OR (i.inprocess_batch_id IS NULL AND i.inspection_type='INPROCESS' AND i.plant=b.plant AND i.inspection_at=b.inspection_at AND i.entity_id=b.product_id AND i.sampling_location=b.tank AND i.created_by_badge=b.created_by_badge AND BINARY i.batch_no=BINARY b.batch_no))) AS has_records,
            EXISTS(SELECT 1 FROM t_qc_inprocess_batch child WHERE child.parent_batch_id=b.id) AS has_followup
            FROM t_qc_inprocess_batch b WHERE b.plant=? AND b.inspection_at=? AND b.root_batch_id=0";
        $args=[$plant,$at];
        if($badge!==''){$sql.=' AND b.created_by_badge=?';$args[]=$badge;}
        $query=$pdo->prepare($sql.' ORDER BY b.updated_at DESC,b.id DESC'.($lock?' FOR UPDATE':''));$query->execute($args);return $query->fetchAll();
    }

    /** Retain measured/history rows; collapse unused per-user drafts to one shared initial entry. */
    private static function preferredRoots(array $roots,string $prefix,bool $hasLegacy=false): array {
        $recorded=array_values(array_filter($roots,fn($root)=>!empty($root['has_records'])||!empty($root['has_followup'])));
        if($recorded)return $recorded;
        if($hasLegacy)return [];
        $specific=fn($root)=>trim((string)$root['batch_no'])!==''&&strcasecmp(trim((string)$root['batch_no']),trim($prefix))!==0;
        usort($roots,fn($a,$b)=>($specific($b)<=>$specific($a))?:strcmp($b['updated_at'],$a['updated_at'])?:((int)$b['id']<=>(int)$a['id']));
        return $roots?[$roots[0]]:[];
    }

    public static function attach(PDO $pdo,array &$rows,string $plant,string $date,string $shift,string $badge,array &$cache=[],string $viewerBadge=''): void {
        qc_tracking_storage($pdo);
        require_once __DIR__.'/InprocessRuns.php';
        require_once __DIR__.'/InprocessGrid.php';
        $context=$plant.'|'.$date.'|'.$shift.'|'.$badge;
        if(!isset($cache['batches'][$context])){
            $batches=[];
            foreach(self::roots($pdo,$plant,$date.' '.$shift,$badge) as $batch){$key=$batch['location_id'].':'.$batch['product_id'];$batches[$key][]=$batch;}
            $cache['batches'][$context]=$batches;
        }
        if(!isset($cache['legacy'][$context])){
            $sql="SELECT i.*,r.id AS result_id,r.spec_rule_id,r.parameter_code,r.parameter_name,r.result_value,r.result_text,r.unit,r.disposition,r.raw_input_json,COALESCE(NULLIF(r.created_by_name,''),i.created_by_name) AS modified_by_name
                FROM t_qc_inspection i JOIN t_qc_result r ON r.inspection_id=i.id AND r.attempt_no=1
                WHERE i.inspection_type='INPROCESS' AND i.status<>'CANCELLED' AND i.inprocess_batch_id IS NULL AND i.plant=? AND i.inspection_at=?";
            $args=[$plant,$date.' '.$shift];
            if($badge!==''){$sql.=' AND i.created_by_badge=?';$args[]=$badge;}
            $query=$pdo->prepare($sql.' ORDER BY i.updated_at DESC,i.id DESC,r.id DESC');$query->execute($args);$legacy=[];
            foreach($query->fetchAll() as $record){
                $tankKey=(int)$record['entity_id'].'|'.strtoupper($record['sampling_location']);
                $identity=self::legacyKey($record['created_by_badge'],(string)$record['batch_no']);
                $legacy[$tankKey][$identity][]=$record;
            }
            $cache['legacy'][$context]=$legacy;
        }
        $batches=$cache['batches'][$context];$expanded=[];
        foreach($rows as $row){
            $roots=$batches[$row['location_id'].':'.$row['product_id']]??[];
            $legacy=$cache['legacy'][$context][$row['product_id'].'|'.strtoupper($row['tank'])]??[];
            foreach($roots as &$root)if(isset($legacy[self::legacyKey($root['created_by_badge'],(string)$root['batch_no'])]))$root['has_records']=true;
            unset($root);
            $roots=self::preferredRoots($roots,(string)($row['product_batch_code']??''),(bool)$legacy);
            if(!$roots&&!$legacy){
                $row['run_no']=1;$row['run_id']=0;$row['root_batch_id']=0;
                $row['is_read_only']=$viewerBadge!==''&&(bool)array_filter($row['saved_by_spec']??[],fn($saved)=>!empty($saved['created_by_badge'])&&(string)$saved['created_by_badge']!==$viewerBadge);
                if(!empty($row['saved_by_spec'])){
                    $saved=reset($row['saved_by_spec']);
                    $row['legacy_key']=self::legacyKey((string)($saved['created_by_badge']??''),(string)($saved['batch_no']??$row['batch']??''));
                }
                $expanded[]=$row;
                continue;
            }
            foreach($roots as $batch){
                $id=(int)$batch['id'];
                // Some old readings have a matching original batch but predate its explicit link.
                // Resolve that association only in memory; loading a summary must not migrate data.
                $legacyKey=self::legacyKey($batch['created_by_badge'],(string)$batch['batch_no']);
                $originalRecords=$legacy[$legacyKey]??[];unset($legacy[$legacyKey]);
                if(!isset($cache['chains'][$id])){
                    $q=$pdo->prepare('SELECT * FROM t_qc_inprocess_batch WHERE id=? OR root_batch_id=? ORDER BY run_no');$q->execute([$id,$id]);$cache['chains'][$id]=$q->fetchAll();
                }
                foreach($cache['chains'][$id] as $run){
                    $copy=$row;$preset=json_decode($run['preset_json']??'{}',true)?:[];
                    $copy['run_id']=(int)$run['id'];$copy['run_no']=(int)$run['run_no'];$copy['parent_batch_id']=$run['parent_batch_id'];
                    $copy['root_batch_id']=$id;
                    // The current test is shared; session permissions and history still control editing.
                    $copy['is_read_only']=false;
                    $copy['is_history']=(int)$run['id']!==(int)end($cache['chains'][$id])['id'];
                    $copy['sample_type']=$preset['sample_type']??'NEW_SAMPLE';$copy['batch']=$run['batch_no'];
                    $copy['batch_saved']=true;$copy['batch_modified']=$run['updated_at'];$copy['batch_author']=$run['modified_by_name'];
                    // Restore every row from its exact run, including original runs.
                    unset($copy['saved_by_spec']);$single=[$copy];
                    $records=$cache['records'][$run['id']]??($cache['records'][$run['id']]=InprocessRuns::records($pdo,(int)$run['id']));
                    if((int)$run['id']===$id&&$originalRecords){
                        $records=array_merge($records,$originalRecords);
                        usort($records,fn($a,$b)=>strcmp($b['updated_at'],$a['updated_at'])?:((int)$b['id']<=>(int)$a['id']));
                    }
                    foreach($records as &$r)$r['modified_by_name']=$r['modified_by_name']??$r['created_by_name'];unset($r);
                    InprocessGrid::attachSavedRows($single,$records);$copy=$single[0];
                    $expanded[]=$copy;
                }
            }
            foreach($legacy as $identity=>$records){
                $source=$records[0];$copy=self::blankEntry($row);
                $copy['legacy_key']=$identity;$copy['batch']=$source['batch_no'];$copy['batch_saved']=true;
                $copy['is_read_only']=($viewerBadge!==''&&(string)$source['created_by_badge']!==$viewerBadge)||(bool)array_filter($records,fn($record)=>$record['status']!=='DRAFT');
                $single=[$copy];InprocessGrid::attachSavedRows($single,$records);$expanded[]=$single[0];
            }
        }
        $rows=$expanded;
    }

    private static function legacyKey(string $badge,string $batch): string {
        return 'legacy-'.hash('sha256',json_encode([$badge,$batch],JSON_THROW_ON_ERROR));
    }

    private static function blankEntry(array $row): array {
        unset($row['saved_by_spec'],$row['batch_modified'],$row['batch_author'],$row['legacy_key']);
        return array_replace($row,['batch'=>$row['product_batch_code']??'','batch_saved'=>false,'run_id'=>0,'run_no'=>1,'root_batch_id'=>0,'parent_batch_id'=>null,'is_history'=>false,'is_read_only'=>false,'sample_type'=>'NEW_SAMPLE']);
    }

    public static function save(PDO $pdo,array $data,array $user,bool $validatePreset=true): array {
        $plant=LinePreset::plantKey((string)($data['plant']??''));
        $date=LinePreset::dateKey((string)($data['date']??''));
        $shift=LinePreset::shiftKey((string)($data['sampling_time']??''));
        $pid=(int)($data['product_id']??0);$locationId=(int)($data['location_id']??0);
        $product=qc_master_product_by_id($pid);$location=null;
        foreach(qc_master_active('locations') as $candidate)if((int)$candidate['id']===$locationId&&$candidate['factory_code']===$plant)$location=$candidate;
        if(!$product||!$location)throw new InvalidArgumentException('Select an applicable product and tank.');
        $batch=trim((string)($data['batch_no']??''));
        if($batch===''||qc_text_length($batch)>100)throw new InvalidArgumentException('Enter a batch number of up to 100 characters.');
        require_once __DIR__.'/InprocessRuns.php';
        $previousRun=null;
        if(!empty($data['run_id'])){
            $run=InprocessRuns::get($pdo,(int)$data['run_id'],true);
            if($run['plant']!==$plant||$run['inspection_at']!==$date.' '.$shift||(int)$run['location_id']!==$locationId||(int)$run['product_id']!==$pid)throw new InvalidArgumentException('Test context changed. Reload Inprocess.');
            InprocessRuns::assertLatest($pdo,$run);
            if($run['parent_batch_id']){
                if($run['batch_no']!==$batch)throw new InvalidArgumentException('Follow-up batch numbers are locked to the original test.');
                return ['run_id'=>(int)$run['id'],'batch_no'=>$batch,'sample_type'=>(json_decode($run['preset_json']??'{}',true)?:[])['sample_type']??'NEW_SAMPLE','modified_by_name'=>$run['modified_by_name'],'modified'=>$run['updated_at']];
            }
            $roots=array_values(array_filter(self::roots($pdo,$plant,$date.' '.$shift,'',true),fn($root)=>(int)$root['location_id']===$locationId&&(int)$root['product_id']===$pid));
            if(!in_array((int)$run['id'],array_map(fn($root)=>(int)$root['id'],self::preferredRoots($roots,(string)$product['batch_code'])),true))throw new InvalidArgumentException('This tank already has a saved batch. Reload Inprocess to use the shared row.');
            $previousRun=$run;
        }
        $entry=$data['preset']??null;
        if($validatePreset){
            $preset=LinePreset::forShift($pdo,$plant,$date,$shift);
            $entry=LinePreset::gridEntry($preset['rows'],qc_master_data()['locations'],$pid,$location['location_code'],[]);
            if(!$entry)throw new InvalidArgumentException('Reload Inprocess: this tank is no longer running.');
        }
        if(!$previousRun){
            $roots=array_values(array_filter(self::roots($pdo,$plant,$date.' '.$shift,'',true),fn($root)=>(int)$root['location_id']===$locationId&&(int)$root['product_id']===$pid));
            $previousRun=self::preferredRoots($roots,(string)$product['batch_code'])[0]??null;
            if($previousRun&&$previousRun['batch_no']!==$batch)throw new InvalidArgumentException('This tank already has a saved batch. Reload Inprocess to use the shared row.');
        }
        $stored=json_decode($previousRun['preset_json']??'{}',true)?:[];
        if($previousRun)InprocessRuns::assertLatest($pdo,$previousRun);
        $sampleType='NEW_SAMPLE';
        if(isset($data['sample_type'])&&$data['sample_type']!=='NEW_SAMPLE')throw new InvalidArgumentException('Use Follow-up test to create a separate Retest, Rework or New Sample row.');
        $entry=$entry??$stored;$entry['sample_type']=$sampleType;
        if($previousRun){
            $runId=(int)$previousRun['id'];
            $pdo->prepare('UPDATE t_qc_inprocess_batch SET batch_no=?,preset_json=?,modified_by_name=?,updated_at=NOW() WHERE id=?')->execute([$batch,json_encode($entry,JSON_THROW_ON_ERROR),$user['name'],$runId]);
        }else{
            $statement=$pdo->prepare('INSERT INTO t_qc_inprocess_batch (plant,inspection_at,location_id,product_id,tank,batch_no,preset_json,created_by_badge,modified_by_name) VALUES (?,?,?,?,?,?,?,?,?)');
            $statement->execute([$plant,$date.' '.$shift,$locationId,$pid,$location['location_code'],$batch,json_encode($entry,JSON_THROW_ON_ERROR),$user['badge'],$user['name']]);$runId=(int)$pdo->lastInsertId();
        }
        $update=$pdo->prepare("UPDATE t_qc_inspection SET batch_no=?,inprocess_batch_id=?,updated_at=NOW() WHERE inspection_type='INPROCESS' AND status='DRAFT' AND plant=? AND entity_id=? AND sampling_location=? AND inspection_at=? AND (inprocess_batch_id=? OR (inprocess_batch_id IS NULL AND created_by_badge=? AND BINARY batch_no=BINARY ?))");
        $update->execute([$batch,$runId,$plant,$pid,$location['location_code'],$date.' '.$shift,$runId,$previousRun['created_by_badge']??$user['badge'],$previousRun['batch_no']??$batch]);
        return ['run_id'=>$runId,'batch_no'=>$batch,'sample_type'=>$sampleType,'modified_by_name'=>$user['name'],'modified'=>date('Y-m-d H:i:s')];
    }
}
