<?php
require_once __DIR__.'/InprocessGrid.php';

/** Running tanks and their enabled tests, stored separately from inspection results. */
class LinePreset {
    public static function plantKey(string $plant): string {
        if(!in_array($plant,['F21RBD','F40ARP'],true))throw new InvalidArgumentException('Select a valid plant.');
        return $plant;
    }

    /** Older installations stored a setup for each date and shift. Keep the most recently saved setup per plant. */
    public static function normalizeStorage(array $data): array {
        if(is_array($data['plants']??null))return ['version'=>2,'plants'=>$data['plants']];
        if(!is_array($data['contexts']??null))throw new RuntimeException('Line preset data could not be read.');
        $plants=[];$selected=[];
        foreach($data['contexts'] as $key=>$preset){
            $plant=explode('|',(string)$key)[0];
            if(!in_array($plant,['F21RBD','F40ARP'],true)||!is_array($preset['rows']??null))continue;
            $rank=[strtotime((string)($preset['updated_at']??''))?:0,(string)$key];
            if(!isset($selected[$plant])||$rank>$selected[$plant]){
                $plants[$plant]=$preset;$selected[$plant]=$rank;
            }
        }
        return ['version'=>2,'plants'=>$plants];
    }

    private static function path(): string {return __DIR__.'/../data/line_presets.json';}

    private static function read(): array {
        if(!is_file(self::path()))return ['version'=>2,'plants'=>[]];
        $data=json_decode((string)file_get_contents(self::path()),true);
        if(!is_array($data))throw new RuntimeException('Line preset data could not be read.');
        return self::normalizeStorage($data);
    }

    public static function rows(string $plant): array {
        return self::read()['plants'][self::plantKey($plant)]['rows']??[];
    }

    public static function dateKey(string $date): string {
        $parsed=DateTime::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Select a valid inspection date.');
        return $date;
    }

    public static function shiftKey(string $samplingTime): string {
        if(!in_array($samplingTime,['08:00:00','20:00:00'],true))throw new InvalidArgumentException('Select Day or Night sampling time.');
        return $samplingTime;
    }

    /** Saved setups belong to one shift; current running tanks are also available in its summary. */
    public static function forShift(PDO $pdo,string $plant,string $date,string $samplingTime): array {
        self::plantKey($plant);self::dateKey($date);self::shiftKey($samplingTime);
        $st=$pdo->prepare("SELECT i.id,i.entity_id,i.sampling_location,r.parameter_code,r.parameter_name,r.raw_input_json
            FROM t_qc_inspection i JOIN t_qc_result r ON r.inspection_id=i.id AND r.attempt_no=1
            WHERE i.inspection_type='INPROCESS' AND i.status<>'CANCELLED' AND i.plant=?
              AND i.inspection_at=?
              AND (r.result_value IS NOT NULL OR NULLIF(TRIM(r.result_text),'') IS NOT NULL)
            ORDER BY i.id ASC,r.id ASC");
        $st->execute([$plant,$date.' '.$samplingTime]);
        $saved=$st->fetchAll();
        $current=self::rows($plant);
        $batchRows=[];
        if(table_exists($pdo,'t_qc_inprocess_batch')){
            $batches=$pdo->prepare('SELECT * FROM t_qc_inprocess_batch WHERE plant=? AND inspection_at=? ORDER BY updated_at DESC,id DESC');
            $batches->execute([$plant,$date.' '.$samplingTime]);
            $seenBatch=[];
            foreach($batches->fetchAll() as $batch){
                $batchKey=$batch['location_id'].':'.$batch['product_id'];if(isset($seenBatch[$batchKey]))continue;
                $entry=json_decode($batch['preset_json']??'',true);
                if(is_array($entry)&&!empty($entry['parameters'])&&(int)($entry['location_id']??0)===(int)$batch['location_id']&&(int)($entry['product_id']??0)===(int)$batch['product_id']){$batchRows[]=$entry;$seenBatch[$batchKey]=true;}
            }
        }
        if(!$saved){
            if($batchRows)return ['rows'=>self::withCurrentRunning($batchRows,$current),'snapshot_rows'=>$batchRows,'saved'=>true,'source'=>'batch'];
            return ['rows'=>$current,'snapshot_rows'=>$current,'saved'=>false,'source'=>'current'];
        }
        $recovered=self::recoverRows($plant,$saved,qc_master_data()['locations']);
        foreach($saved as $row){
            $raw=json_decode((string)$row['raw_input_json'],true);
            $snapshot=$raw['line_preset_snapshot']??null;
            // Old snapshots have no shift field. The exact-time query confines them to their original shift.
            if(is_array($snapshot)&&($snapshot['plant']??'')===$plant&&($snapshot['date']??'')===$date&&($snapshot['sampling_time']??$samplingTime)===$samplingTime&&!empty($snapshot['rows'])&&is_array($snapshot['rows'])){
                $historical=self::mergeSavedRows($snapshot['rows'],$recovered);
                $historical=self::mergeSavedRows($historical,$batchRows);
                return ['rows'=>self::withCurrentRunning($historical,$current),'snapshot_rows'=>$snapshot['rows'],'saved'=>true,'source'=>'snapshot'];
            }
        }
        // Before snapshots were recorded, the saved tank/product/parameter identifies the historical setup.
        $recovered=self::mergeSavedRows($recovered,$batchRows);
        return ['rows'=>self::withCurrentRunning($recovered,$current),'snapshot_rows'=>$recovered,'saved'=>true,'source'=>'legacy'];
    }

    public static function withCurrentRunning(array $historical,array $current): array {
        return self::mergeSavedRows($historical,array_values(array_filter($current,fn($row)=>$row['status']==='RUN')));
    }

    public static function mergeSavedRows(array $snapshot,array $recorded): array {
        $rows=[];
        foreach($snapshot as $row)$rows[$row['location_id'].':'.$row['product_id']]=$row;
        foreach($recorded as $row){
            $key=$row['location_id'].':'.$row['product_id'];
            if(!isset($rows[$key]))$rows[$key]=$row;
            else{
                $rows[$key]['status']='RUN';
                $rows[$key]['parameters']=array_values(array_unique(array_merge($rows[$key]['parameters'],$row['parameters'])));
            }
        }
        return array_values($rows);
    }

    public static function recoverRows(string $plant,array $saved,array $locations): array {
        $byCode=[];$rows=[];
        foreach($locations as $location){
            if(strtoupper((string)$location['factory_code'])===$plant)$byCode[strtoupper((string)$location['location_code'])]=$location;
        }
        foreach($saved as $record){
            $location=$byCode[strtoupper((string)$record['sampling_location'])]??null;
            $tab=InprocessGrid::ruleTabKey($record);
            if(!$location||!$tab||(int)$record['entity_id']<1)continue;
            $key=$location['id'].':'.$record['entity_id'];
            if(!isset($rows[$key]))$rows[$key]=[
                'location_id'=>(int)$location['id'],'product_id'=>(int)$record['entity_id'],
                'status'=>'RUN','spec_type'=>'IQS','factory_specification'=>'ALL','parameters'=>[],
            ];
            $raw=json_decode((string)($record['raw_input_json']??''),true);
            $entry=$raw['line_preset_snapshot']['entry']??null;
            if(is_array($entry)&&(int)($entry['location_id']??0)===(int)$location['id']&&(int)($entry['product_id']??0)===(int)$record['entity_id']){
                $rows[$key]['factory_specification']=trim((string)($entry['factory_specification']??''))?:'ALL';
                $rows[$key]['parameters']=array_values(array_unique(array_merge($rows[$key]['parameters'],$entry['parameters']??[])));
            }
            if(!in_array($tab,$rows[$key]['parameters'],true))$rows[$key]['parameters'][]=$tab;
        }
        return array_values($rows);
    }

    public static function gridEntry(array $presets,array $locations,int $productId,string $tank,array $rules): ?array {
        $byId=array_column($locations,null,'id');
        foreach($presets as $preset){
            $location=$byId[$preset['location_id']]??null;
            if(!$location||$preset['status']!=='RUN'||(int)$preset['product_id']!==$productId||strcasecmp($location['location_code'],$tank)!==0)continue;
            foreach($rules as $rule){
                $tab=InprocessGrid::ruleTabKey($rule);
                if(!$tab||!in_array($tab,$preset['parameters'],true))return null;
            }
            return $preset;
        }
        return null;
    }

    public static function factorySpecifications(array $profiles,int $productId): array {
        $scopes=['ALL'=>'ALL'];
        foreach($profiles as $profile){
            if(($profile['entity_type']??'')!=='PRODUCT'||(int)($profile['entity_id']??0)!==$productId)continue;
            if(isset($profile['active'])&&!$profile['active'])continue;
            if(!in_array(strtoupper((string)($profile['spec_type']??'')),['IQS','QS'],true))continue;
            $scope=trim((string)($profile['factory_scope']??''));
            $scope=preg_replace('/\s+/',' ',$scope);
            if($scope===''||strtoupper($scope)==='ALL')continue;
            if(!isset($scopes[strtoupper($scope)]))$scopes[strtoupper($scope)]=$scope;
        }
        $other=array_values(array_diff_key($scopes,['ALL'=>true]));
        usort($other,'strnatcasecmp');
        return array_merge(['ALL'],$other);
    }

    public static function catalog(PDO $pdo,string $plant): array {
        $data=InprocessGrid::dbPayload($pdo,$plant,'ALL');
        $profiles=qc_master_active('spec_profiles');
        $locations=[];
        foreach(qc_master_active('locations') as $location){
            if(strtoupper($location['factory_code'])!==$plant)continue;
            $locations[(int)$location['id']]=[
                'location_id'=>(int)$location['id'],'tank'=>$location['location_code'],
                'factory'=>$plant,'products'=>[],
            ];
        }
        foreach($data['parameter_rows'] as $tab=>$rows){
            foreach($rows as $row){
                $id=$row['location_id'];$pid=$row['product_id'];
                if(!isset($locations[$id]['products'][$pid])){
                    $locations[$id]['products'][$pid]=['product_id'=>$pid,'name'=>$row['product'],'batch'=>$row['product_batch_code'],'specs'=>[]];
                }
                foreach($row['spec_options'] as $option){
                    $spec=strtoupper($option['spec_type']);
                    $locations[$id]['products'][$pid]['specs'][$spec][]=$tab;
                }
            }
        }
        foreach($locations as &$location){
            $location['products']=array_values($location['products']);
            foreach($location['products'] as &$product){
                $product['factory_specifications']=self::factorySpecifications($profiles,(int)$product['product_id']);
                foreach($product['specs'] as &$keys)$keys=array_values(array_unique($keys));
                unset($keys);
            }
            unset($product);
        }
        unset($location);
        $locations=array_values($locations);
        usort($locations,fn($a,$b)=>strnatcasecmp($a['tank'],$b['tank']));
        return ['locations'=>$locations,'parameters'=>array_values(array_filter(InprocessGrid::tabs(),fn($t)=>$t['key']!=='VTQ'))];
    }

    public static function validate(array $rows,array $catalog): array {
        $locations=array_column($catalog['locations'],null,'location_id');$seen=[];$out=[];
        foreach($rows as $row){
            $id=(int)($row['location_id']??0);
            if(!isset($locations[$id])||isset($seen[$id]))throw new InvalidArgumentException('Each tank must appear once and belong to this plant.');
            $seen[$id]=true;
            $status=strtoupper((string)($row['status']??'STOP'));
            if(!in_array($status,['RUN','STOP'],true))throw new InvalidArgumentException('Tank status must be Run or Stop.');
            $pid=(int)($row['product_id']??0);$spec='IQS';
            $products=array_column($locations[$id]['products'],null,'product_id');
            if($pid!==0&&!isset($products[$pid]))throw new InvalidArgumentException('Selected product is not applicable to '.$locations[$id]['tank'].'.');
            $scope=trim((string)($row['factory_specification']??'ALL'));
            if($scope===''||strtoupper($scope)==='ALL')$scope='ALL';
            $scopes=$products[$pid]['factory_specifications']??['ALL'];
            if(!in_array($scope,$scopes,true))throw new InvalidArgumentException('Choose a factory specification applicable to this product.');
            $allowed=$products[$pid]['specs'][$spec]??[];
            $requested=$row['parameters']??[];
            if(!is_array($requested))throw new InvalidArgumentException('Invalid parameter selection.');
            $enabled=array_values(array_unique(array_map(fn($t)=>InprocessGrid::normalizeTab((string)$t),$requested)));
            if(array_diff($enabled,$allowed))throw new InvalidArgumentException('Enable only tests applicable to the selected product and specification.');
            if($status==='RUN'&&(!$pid||!$allowed||!$enabled))throw new InvalidArgumentException('A running tank needs a product, a specification and at least one enabled test.');
            $out[]=['location_id'=>$id,'product_id'=>$pid,'status'=>$status,'spec_type'=>$spec,'factory_specification'=>$scope,'parameters'=>$enabled];
        }
        return $out;
    }

    public static function save(string $plant,array $rows,array $catalog,array $user): void {
        $key=self::plantKey($plant);
        $validated=self::validate($rows,$catalog);
        self::update(function($data)use($key,$validated,$user){
            $data['plants'][$key]=['rows'=>$validated,'updated_at'=>date('c'),'updated_by'=>$user['name']];
            return $data;
        });
    }

    private static function update(callable $change): void {
        $lock=fopen(self::path().'.lock','c');
        if(!$lock)throw new RuntimeException('The server cannot write line presets in the data folder.');
        $tmp=null;
        try{
            if(!flock($lock,LOCK_EX))throw new RuntimeException('Unable to lock the line preset file.');
            $data=$change(self::read());
            $tmp=tempnam(dirname(self::path()),'preset-');
            if(!$tmp||file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false||!rename($tmp,self::path())){
                throw new RuntimeException('Unable to save line presets. Check that the server can write to the data folder.');
            }
        }finally{
            if($tmp&&is_file($tmp))unlink($tmp);
            flock($lock,LOCK_UN);fclose($lock);
        }
    }

    public static function runningTankCount(array $parameterRows): int {
        $tanks=[];
        foreach($parameterRows as $rows){
            foreach($rows as $row)$tanks[(int)$row['location_id']]=true;
        }
        return count($tanks);
    }

    /** Historical setups keep saved rows visible but must not create new empty work for stopped lines. */
    public static function filterEmptyEntries(array $rows,array $current,string $tab): array {
        $running=[];$tab=InprocessGrid::normalizeTab($tab);
        foreach($current as $preset){
            if($preset['status']==='RUN'&&in_array($tab,$preset['parameters']??[],true))$running[$preset['location_id'].':'.$preset['product_id']]=true;
        }
        return array_values(array_filter($rows,function($row)use($running){
            if(!empty($row['run_id'])||!empty($row['batch_saved'])||!empty($row['saved_by_spec']))return true;
            return isset($running[$row['location_id'].':'.$row['product_id']]);
        }));
    }

    public static function apply(array $data,array $presets): array {
        $running=[];
        foreach($presets as $preset){
            if($preset['status']==='RUN')$running[$preset['location_id'].':'.$preset['product_id']]=$preset;
        }
        foreach($data['parameter_rows'] as $tab=>&$rows){
            $filtered=[];
            foreach($rows as $row){
                $preset=$running[$row['location_id'].':'.$row['product_id']]??null;
                if(!$preset||!in_array($tab,$preset['parameters'],true))continue;
                $option=null;
                foreach($row['spec_options'] as $candidate){if(strtoupper($candidate['spec_type'])==='IQS'){$option=$candidate;break;}}
                if(!$option)continue;
                $row['spec_type']='IQS';$row['profile_id']=$option['profile_id'];$row['rules']=$option['rules'];
                $row['factory_specification']=trim((string)($preset['factory_specification']??''))?:'ALL';
                $filtered[]=$row;
            }
            $rows=$filtered;
        }
        unset($rows);
        foreach($data['tabs'] as &$tab)$tab['count']=count($data['parameter_rows'][InprocessGrid::normalizeTab($tab['key'])]??[]);
        unset($tab);
        $data['running_count']=self::runningTankCount($data['parameter_rows']);$data['preset_count']=count($presets);
        return $data;
    }
}
