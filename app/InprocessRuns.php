<?php
require_once __DIR__.'/InprocessBatch.php';

/** Each follow-up owns editable readings without changing its source run. */
class InprocessRuns {
    public static function scope(string $type,string $scope='FAILED'): string {
        InprocessBatch::sampleType($type);
        if(!in_array($scope,['FAILED','ALL'],true))throw new InvalidArgumentException('Choose Failed only or All parameters.');
        return $type==='NEW_SAMPLE'?'ALL':$scope;
    }
    public static function fingerprint(array $records): string {
        $source=[];
        foreach($records as $r)$source[]=[$r['id'],$r['result_id'],$r['updated_at'],$r['result_value'],$r['result_text'],$r['disposition'],$r['raw_input_json']];
        return hash('sha256',json_encode($source,JSON_THROW_ON_ERROR));
    }
    /** Create and fill a follow-up in one transaction. Opening the drawer never writes data. */
    public static function save(PDO $pdo,array $data,array $user): array {
        require_once __DIR__.'/spec.php';require_once __DIR__.'/Calculation.php';
        $id=(int)($data['run_id']??0);$parent=self::get($pdo,$id,true);
        $type=(string)($data['sample_type']??'');$scope=self::scope($type,(string)($data['scope']??'FAILED'));
        $requestId=(string)($data['request_id']??'');
        $payloadHash=hash('sha256',json_encode([$type,$scope,$data['readings']??[]],JSON_THROW_ON_ERROR));
        if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$requestId))throw new InvalidArgumentException('Reload the form and try again.');
        if($child=self::child($pdo,$id)){
            $preset=json_decode($child['preset_json']??'{}',true)?:[];
            if(($preset['followup_request_id']??'')===$requestId){
                if(($preset['followup_payload_hash']??'')!==$payloadHash)throw new InvalidArgumentException('This test was already saved. Reload Inprocess to see the saved readings.');
                return ['run_id'=>(int)$child['id'],'sample_type'=>$preset['sample_type'],'existing'=>true];
            }
            throw new InvalidArgumentException('A follow-up has already been saved. Reload Inprocess.');
        }
        $source=self::records($pdo,$id);
        if(!hash_equals(self::fingerprint($source),(string)($data['source_token']??'')))throw new InvalidArgumentException('The previous results changed. Reopen the form to use the latest results.');
        $readings=$data['readings']??[];
        if(!is_array($readings)||!$readings)throw new InvalidArgumentException('Enter the parameter readings before saving.');
        $prepared=[];
        foreach($readings as $reading){
            $matches=array_values(array_filter($source,fn($r)=>(int)$r['id']===(int)($reading['source_inspection_id']??0)&&(int)$r['spec_rule_id']===(int)($reading['rule_id']??0)));
            $r=$matches[0]??null;
            if(!$r||($scope==='FAILED'&&$r['disposition']==='PASS'))throw new InvalidArgumentException('Choose All parameters to enter new readings for a passing parameter.');
            $key=$r['spec_type'].':'.$r['parameter_code'];if(isset($prepared[$key]))throw new InvalidArgumentException('A parameter was submitted twice.');
            $profile=get_spec_profile_by_id($pdo,(int)$r['spec_profile_id']);$rule=null;
            foreach($profile['rules']??[] as $candidate)if((int)$candidate['id']===(int)$r['spec_rule_id'])$rule=$candidate;
            if(!$rule)throw new InvalidArgumentException('The product specification changed. Reload Inprocess.');
            $raw=$reading['raw_samples']??[];
            if(!is_array($raw)||count($raw)>(int)($rule['sample_count']?:3))throw new InvalidArgumentException('Invalid sample readings.');
            $values=[];
            foreach($raw as $sample){
                if(!is_array($sample))throw new InvalidArgumentException('Invalid sample readings.');
                if(!array_filter($sample,fn($v)=>$v!==''&&$v!==null))continue;
                $result=!empty($rule['method_key'])?qc_calculate((int)$rule['method_key'],$sample)['result']:qc_num($sample['A']??null);
                if(!is_finite((float)$result))throw new InvalidArgumentException('Invalid calculated result.');
                $values[]=$result;
            }
            if(!$values)throw new InvalidArgumentException('Enter at least one complete sample for '.$r['parameter_name'].'.');
            $value=array_sum($values)/count($values);
            $prepared[$key]=['source'=>$r,'rule'=>$rule,'value'=>$value,'values'=>$values,'raw'=>$raw];
        }
        foreach($source as $r)if(($scope==='ALL'||$r['disposition']!=='PASS')&&!isset($prepared[$r['spec_type'].':'.$r['parameter_code']]))throw new InvalidArgumentException('Enter readings for '.$r['parameter_name'].'.');
        $saved=self::create($pdo,$id,$type,$user,$scope);
        $newId=$saved['run_id'];$children=[];
        foreach(self::records($pdo,$newId) as $r)$children[$r['spec_type'].':'.$r['parameter_code']]=$r;
        foreach($prepared as $key=>$p){
            $target=$children[$key];$raw=json_decode($target['raw_input_json']??'{}',true)?:[];
            $raw['raw_samples']=$p['raw'];$raw['samples']=$p['values'];unset($raw['carry_forward']);
            $status=InprocessGrid::disposition(evaluate_rule($p['rule'],$p['value']));
            $pdo->prepare('UPDATE t_qc_result SET result_value=?,result_text=NULL,disposition=?,raw_input_json=?,created_by_badge=?,created_by_name=? WHERE id=?')->execute([$p['value'],$status,json_encode($raw,JSON_THROW_ON_ERROR),$user['badge'],$user['name'],$target['result_id']]);
            $pdo->prepare('UPDATE t_qc_inspection SET overall_disposition=?,updated_at=NOW() WHERE id=?')->execute([$status,$target['id']]);
        }
        $preset=json_decode(self::get($pdo,$newId)['preset_json']??'{}',true)?:[];$preset['followup_request_id']=$requestId;$preset['followup_payload_hash']=$payloadHash;
        $pdo->prepare('UPDATE t_qc_inprocess_batch SET preset_json=? WHERE id=?')->execute([json_encode($preset,JSON_THROW_ON_ERROR),$newId]);
        return $saved+['sample_type'=>$data['sample_type']];
    }
    public static function get(PDO $pdo,int $id,bool $lock=false): array {
        $q=$pdo->prepare('SELECT * FROM t_qc_inprocess_batch WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$id]);
        $run=$q->fetch();if(!$run)throw new InvalidArgumentException('Test not found. Reload Inprocess.');
        return $run;
    }
    public static function child(PDO $pdo,int $id): ?array {
        $q=$pdo->prepare('SELECT * FROM t_qc_inprocess_batch WHERE parent_batch_id=?');$q->execute([$id]);
        return $q->fetch()?:null;
    }
    public static function assertLatest(PDO $pdo,array $run): void {
        if(self::child($pdo,(int)$run['id']))throw new InvalidArgumentException('This test is history. Open the latest row to enter readings.');
    }
    public static function records(PDO $pdo,int $id): array {
        $q=$pdo->prepare("SELECT i.*,r.id AS result_id,r.spec_rule_id,r.parameter_code,r.parameter_name,r.result_value,r.result_text,r.unit,r.disposition,r.raw_input_json FROM t_qc_inspection i JOIN t_qc_result r ON r.inspection_id=i.id AND r.attempt_no=1 WHERE i.inprocess_batch_id=? AND i.status<>'CANCELLED' ORDER BY i.updated_at DESC,i.id DESC,r.id DESC");
        $q->execute([$id]);$by=[];
        foreach($q->fetchAll() as $r){$key=$r['spec_type'].':'.$r['parameter_code'];if(!isset($by[$key]))$by[$key]=$r;}
        return array_values($by);
    }
    public static function assertWritable(PDO $pdo,array $run,array $rules,array $data): void {
        self::assertLatest($pdo,$run);
        if($run['plant']!==$data['plant']||(int)$run['product_id']!==(int)$data['entity_id']||$run['tank']!==$data['sampling_location']||$run['inspection_at']!==$data['inspection_at']||$run['batch_no']!==$data['batch_no'])throw new InvalidArgumentException('The selected test does not match these readings. Reload Inprocess.');
    }
    /** Caller holds a transaction; the parent lock serializes duplicate follow-up requests. */
    public static function create(PDO $pdo,int $id,string $type,array $user,string $scope='FAILED'): array {
        $scope=self::scope($type,$scope);$parent=self::get($pdo,$id,true);
        if($child=self::child($pdo,$id)){
            $stored=json_decode($child['preset_json']??'{}',true)?:[];
            if(($stored['sample_type']??'')!==$type||($stored['followup_scope']??self::scope($type))!==$scope)throw new InvalidArgumentException('A follow-up already exists. Reload Inprocess to open it.');
            return ['run_id'=>(int)$child['id'],'run_no'=>(int)$child['run_no'],'existing'=>true];
        }
        // Attach older readings that predate explicit run IDs without changing their values or timestamps.
        $pdo->prepare("UPDATE t_qc_inspection SET inprocess_batch_id=?,updated_at=updated_at WHERE inspection_type='INPROCESS' AND inprocess_batch_id IS NULL AND plant=? AND inspection_at=? AND entity_id=? AND sampling_location=? AND created_by_badge=? AND BINARY batch_no=BINARY ?")
            ->execute([$id,$parent['plant'],$parent['inspection_at'],$parent['product_id'],$parent['tank'],$parent['created_by_badge'],$parent['batch_no']]);
        $records=self::records($pdo,$id);
        if(!$records)throw new InvalidArgumentException('Save readings before creating a follow-up.');
        if($scope==='FAILED'&&!array_filter($records,fn($r)=>$r['disposition']!=='PASS'))throw new InvalidArgumentException('No failed parameters. Choose All parameters.');
        $preset=json_decode($parent['preset_json']??'{}',true)?:[];
        unset($preset['followup_request_id'],$preset['followup_payload_hash']);
        $preset['sample_type']=$type;$preset['followup_scope']=$scope;
        $q=$pdo->prepare('INSERT INTO t_qc_inprocess_batch (plant,inspection_at,location_id,product_id,tank,batch_no,preset_json,created_by_badge,modified_by_name,run_no,parent_batch_id,root_batch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        // Keep the same owner/context so each operator sees the complete chain; record the actual editor separately.
        $q->execute([$parent['plant'],$parent['inspection_at'],$parent['location_id'],$parent['product_id'],$parent['tank'],$parent['batch_no'],json_encode($preset,JSON_THROW_ON_ERROR),$parent['created_by_badge'],$user['name'],(int)$parent['run_no']+1,$id,$parent['root_batch_id']?:$id]);
        $newId=(int)$pdo->lastInsertId();$copied=0;
        foreach($records as $r){
            $passed=$scope==='FAILED'&&$r['disposition']==='PASS';
            $source=$pdo->prepare('SELECT * FROM t_qc_inspection WHERE id=?');$source->execute([$r['id']]);$inspection=$source->fetch();
            unset($inspection['id']);
            $inspection['inspection_no']='IP-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(5)));
            $inspection['inprocess_batch_id']=$newId;$inspection['overall_disposition']=$passed?'PASS':'PENDING';
            $inspection['created_by_badge']=$user['badge'];$inspection['created_by_name']=$user['name'];
            $inspection['created_at']=$inspection['updated_at']=date('Y-m-d H:i:s');
            $newInspection=self::insert($pdo,'t_qc_inspection',$inspection);
            $raw=json_decode($r['raw_input_json']??'{}',true)?:[];
            unset($raw['carry_forward']);
            if(!$passed){$raw['samples']=[];$raw['raw_samples']=[];unset($raw['calculator']);}
            if($passed)$raw['carry_forward']=['source_result_id'=>(int)$r['result_id'],'source_inspection_id'=>(int)$r['id'],'source_batch_id'=>$id,'source_run_no'=>(int)$parent['run_no']];
            self::insert($pdo,'t_qc_result',['inspection_id'=>$newInspection,'spec_rule_id'=>$r['spec_rule_id'],'parameter_code'=>$r['parameter_code'],'parameter_name'=>$r['parameter_name'],'result_value'=>$passed?$r['result_value']:null,'result_text'=>$passed?$r['result_text']:null,'unit'=>$r['unit'],'disposition'=>$passed?'PASS':'PENDING','attempt_no'=>1,'raw_input_json'=>json_encode($raw,JSON_THROW_ON_ERROR),'created_by_badge'=>$user['badge'],'created_by_name'=>$user['name']]);
            if($passed)++$copied;
        }
        return ['run_id'=>$newId,'run_no'=>(int)$parent['run_no']+1,'copied'=>$copied];
    }
    private static function insert(PDO $pdo,string $table,array $row): int {
        $q=$pdo->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')');
        $q->execute(array_values($row));return (int)$pdo->lastInsertId();
    }
}
