<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/master.php';

class InprocessGrid {
    public static function disposition(string $value): string {
        return in_array($value,['PASS','FAIL'],true)?$value:'PENDING';
    }

    public static function overallDisposition(array $results): string {
        $states=array_map(fn($result)=>self::disposition((string)($result['disposition']??'')),$results);
        if(in_array('FAIL',$states,true))return 'FAIL';
        return $states&&!in_array('PENDING',$states,true)?'PASS':'PENDING';
    }

    public static function tabs(): array {
        return [
            ['key'=>'PH','label'=>'pH','short'=>'pH'],
            ['key'=>'VCP','label'=>'Viscosity','short'=>'Viscosity (cP)'],
            ['key'=>'VTQ','label'=>'Viscosity','short'=>'Viscosity (Torque%)'],
            ['key'=>'TSC','label'=>'TSC%','short'=>'TSC%'],
            ['key'=>'ALK','label'=>'Alkalinity%','short'=>'Alkalinity%'],
            ['key'=>'CHL','label'=>'Chlorine','short'=>'Chlorine'],
            ['key'=>'FOAM','label'=>'Foaming Height','short'=>'Foaming Height'],
            ['key'=>'ACD','label'=>'Acidity%','short'=>'Acidity%'],
            ['key'=>'BAC','label'=>'Bacteria Content','short'=>'Bacteria Content'],
            ['key'=>'SG','label'=>'Specific Gravity','short'=>'Specific Gravity'],
        ];
    }

    public static function normalizeTab(string $tab): string {
        $tab=strtoupper(trim($tab));
        return $tab==='VTQ'?'VCP':$tab;
    }

    public static function ruleTabKey(array $rule): ?string {
        $code=strtoupper((string)($rule['parameter_code']??''));
        $name=strtolower((string)($rule['parameter_name']??''));
        if($code==='PH'||str_contains($name,'ph'))return 'PH';
        if($code==='VISC'||str_contains($name,'viscos'))return 'VCP';
        if($code==='TSC'||str_contains($name,'solid'))return 'TSC';
        if($code==='ALK'||str_contains($name,'alkalin'))return 'ALK';
        if($code==='CHL'||str_contains($name,'chlor'))return 'CHL';
        if($code==='FOAM'||str_contains($name,'foam'))return 'FOAM';
        if($code==='ACD'||str_contains($name,'acid'))return 'ACD';
        if($code==='BAC'||str_contains($name,'bacteria'))return 'BAC';
        if($code==='SG'||str_contains($name,'specific gravity'))return 'SG';
        return null;
    }

    public static function rowsForTab(array $products,array $locations,array $profiles,array $rules,string $tab,string $plant,string $defaultSpecType='IQS'): array {
        $tab=self::normalizeTab($tab);
        $profilesByProduct=[];
        foreach($profiles as $profile){
            $specType=strtoupper((string)($profile['spec_type']??''));
            if(!in_array($specType,['IQS','QS'],true)||empty($profile['active']))continue;
            $scope=(string)($profile['factory_scope']??'');
            if(function_exists('qc_factory_scope_match')&&!qc_factory_scope_match($scope,$plant))continue;
            $profilesByProduct[(string)$profile['entity_id']][]=$profile;
        }
        $rulesByProfile=[];
        foreach($rules as $rule){
            if(isset($rule['active'])&&!$rule['active'])continue;
            if(self::ruleTabKey($rule)!==$tab)continue;
            $rulesByProfile[(string)$rule['profile_id']][]=$rule;
        }
        $rows=[];$seen=[];
        foreach($locations as $location){
            if(strtoupper((string)($location['factory_code']??''))!==strtoupper($plant))continue;
            if(isset($location['active'])&&!$location['active'])continue;
            foreach($products as $product){
                if(isset($product['active'])&&!$product['active'])continue;
                if(!self::productMatchesLocation($product,$location))continue;
                $productProfiles=$profilesByProduct[(string)$product['id']]??[];
                if(!$productProfiles)continue;
                usort($productProfiles,function($a,$b){
                    $score=fn($p)=>function_exists('qc_factory_scope_score')?qc_factory_scope_score($p['factory_scope']??''):0;
                    return ($score($b)<=>$score($a))?:((int)$b['id']<=>(int)$a['id']);
                });
                $options=[];
                $profileSpecs=[];
                foreach($productProfiles as $profile){
                    $specKey=strtoupper($profile['spec_type']);
                    if(isset($profileSpecs[$specKey]))continue;
                    $profileSpecs[$specKey]=true;
                    $matchingRules=$rulesByProfile[(string)$profile['id']]??[];
                    if(!$matchingRules)continue;
                    $options[]=[
                        'spec_type'=>(string)$profile['spec_type'],
                        'profile_id'=>(int)$profile['id'],
                        'revision'=>(string)($profile['revision']??''),
                        'rules'=>array_values($matchingRules),
                    ];
                }
                if(!$options)continue;
                usort($options,function($a,$b)use($defaultSpecType){
                    $aw=strtoupper($a['spec_type'])===strtoupper($defaultSpecType)?0:1;
                    $bw=strtoupper($b['spec_type'])===strtoupper($defaultSpecType)?0:1;
                    return ($aw<=>$bw)?:strcmp($a['spec_type'],$b['spec_type']);
                });
                $selected=$options[0];
                $key=$location['id'].'-'.$product['id'];
                if(isset($seen[$key]))continue;
                $seen[$key]=true;
                $rows[]=[
                    'location_id'=>(int)$location['id'],
                    'product_id'=>(int)$product['id'],
                    'profile_id'=>(int)$selected['profile_id'],
                    'spec_type'=>(string)$selected['spec_type'],
                    'spec_options'=>$options,
                    'tank'=>(string)$location['location_code'],
                    'factory'=>(string)$location['factory_code'],
                    'product'=>(string)$product['product_name'],
                    'description'=>(string)$product['product_name'],
                    'batch'=>(string)($product['batch_code']??''),
                    'batch_saved'=>false,
                    'product_batch_code'=>(string)($product['batch_code']??''),
                    'product_type'=>(string)($product['product_type']??''),
                    'rules'=>array_values($selected['rules']),
                ];
            }
        }
        usort($rows,fn($a,$b)=>($a['location_id']<=>$b['location_id'])?:strnatcasecmp($a['product'],$b['product']));
        return $rows;
    }

    public static function productMatchesLocation(array $product,array $location): bool {
        $batch=strtoupper((string)($product['batch_code']??''));
        $name=self::norm((string)($product['product_name']??''));
        $type=self::norm((string)($product['product_type']??''));
        $defaultBatch=strtoupper((string)($location['default_batch_code']??''));
        $defaultType=self::norm((string)($location['default_product_type']??''));
        if($batch!==''&&preg_match_all('/[A-Z0-9]+/',$defaultBatch,$m)){
            if(in_array($batch,$m[0],true))return true;
        }
        if($type!==''&&$defaultType!==''){
            if(str_contains($defaultType,$type)||str_contains($type,$defaultType))return true;
            if(str_contains($defaultType,'cleaningagent')&&str_contains($type,'cleaningagent'))return true;
            if(str_contains($defaultType,'polymer')&&str_contains($type,'polymer'))return true;
            if(str_contains($defaultType,'coagulant')&&str_contains($type,'coagulant'))return true;
        }
        if($name!==''&&$defaultType!==''&&str_contains($name,$defaultType))return true;
        return false;
    }

    public static function dbPayload(PDO $pdo,string $plant='F21RBD',string $tab='PH',?string $inspectionDate=null,?string $samplingTime=null,?string $badge=null): array {
        require_once __DIR__.'/spec.php';
        $products=qc_master_products();
        usort($products,fn($a,$b)=>strnatcasecmp(($a['product_type']??'').($a['product_name']??''),($b['product_type']??'').($b['product_name']??'')));
        $locations=qc_master_active('locations');
        usort($locations,fn($a,$b)=>strnatcasecmp(($a['factory_code']??'').($a['location_code']??''),($b['factory_code']??'').($b['location_code']??'')));
        $profiles=array_values(array_filter(qc_master_active('spec_profiles'),fn($p)=>($p['entity_type']??'')==='PRODUCT'&&in_array(strtoupper((string)($p['spec_type']??'')),['IQS','QS'],true)));
        usort($profiles,fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);
        $rules=array_values(array_filter(qc_master_active('spec_rules'),function($r)use($profiles){
            static $profileIds=null;
            if($profileIds===null)$profileIds=array_fill_keys(array_map(fn($p)=>(string)$p['id'],$profiles),true);
            return isset($profileIds[(string)$r['profile_id']]);
        }));
        foreach($rules as &$rule){
            if(!empty($rule['method_key'])){
                $method=qc_master_method_by_key((int)$rule['method_key']);
                if($method)$rule+=$method;
            }
        }
        unset($rule);
        foreach($rules as &$rule)$rule['display']=function_exists('rule_display')?rule_display($rule):(string)($rule['target_text']??'');
        $tabs=[];$parameterRows=[];$allRows=[];$ranges=[];
        foreach(self::tabs() as $info){
            $key=self::normalizeTab($info['key']);
            if(!isset($ranges[$key])){
                $matching=self::rowsForTab($products,$locations,$profiles,$rules,$key,$plant,'IQS');
                $ranges[$key]=[count($allRows),count($matching)];
                array_push($allRows,...$matching);
            }
            $tabs[]=$info+['count'=>$ranges[$key][1]];
        }
        self::attachSavedDrafts($pdo,$allRows,$plant,(string)$inspectionDate,(string)$samplingTime,(string)$badge);
        foreach($ranges as $key=>[$offset,$length])$parameterRows[$key]=array_slice($allRows,$offset,$length);
        if(strtoupper($tab)==='ALL')return ['tabs'=>$tabs,'active'=>'ALL','plant'=>$plant,'parameter_rows'=>$parameterRows];
        $rows=$parameterRows[self::normalizeTab($tab)]??[];
        return ['tabs'=>$tabs,'active'=>strtoupper($tab),'plant'=>$plant,'rows'=>$rows];
    }

    private static function attachSavedDrafts(PDO $pdo,array &$rows,string $plant,string $inspectionDate,string $samplingTime,string $badge=''): void {
        if(!$rows||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$inspectionDate)||!preg_match('/^\d{2}:\d{2}:\d{2}$/',$samplingTime))return;
        $sql="SELECT i.id,i.entity_id,i.spec_type,i.batch_no,i.sampling_location,i.overall_disposition,i.created_by_badge,i.created_by_name,i.updated_at,COALESCE(NULLIF(r.created_by_name,''),i.created_by_name) AS modified_by_name,r.spec_rule_id,r.result_value,r.result_text,r.disposition,r.raw_input_json
              FROM t_qc_inspection i
              JOIN t_qc_result r ON r.inspection_id=i.id AND r.attempt_no=1
              WHERE i.inspection_type='INPROCESS' AND i.status='DRAFT' AND i.plant=? AND DATE(i.inspection_at)=? AND TIME(i.inspection_at)=? AND NOT EXISTS (SELECT 1 FROM t_qc_inprocess_batch child WHERE child.id=i.inprocess_batch_id AND child.parent_batch_id IS NOT NULL)";
        $args=[$plant,$inspectionDate,$samplingTime];
        if($badge!==''){
            $sql.=" AND i.created_by_badge=?";
            $args[]=$badge;
        }
        $sql.=" ORDER BY i.updated_at DESC,i.id DESC";
        $st=$pdo->prepare($sql);
        $st->execute($args);
        self::attachSavedRows($rows,$st->fetchAll());
    }

    /** Attach records for one plant/date/shift/user, ordered newest first by updated_at and id. */
    public static function attachSavedRows(array &$rows,array $records): void {
        $saved=[];$batches=[];
        foreach($records as $r){
            $tankKey=((int)$r['entity_id']).'|'.strtoupper((string)$r['sampling_location']);
            if(!array_key_exists($tankKey,$batches))$batches[$tankKey]=(string)($r['batch_no']??'');
            // A batch edit must not disconnect already saved readings from their tank.
            $key=$tankKey.'|'.strtoupper((string)$r['spec_type']).'|'.((int)$r['spec_rule_id']);
            if(isset($saved[$key]))continue;
            $raw=json_decode((string)($r['raw_input_json']??''),true);
            if(!is_array($raw))$raw=[];
            $modified=(string)($r['updated_at']??'');
            $saved[$key]=[
                'inspection_id'=>(int)$r['id'],
                'rule_id'=>(int)$r['spec_rule_id'],
                'batch_no'=>(string)($r['batch_no']??''),
                'result_value'=>$r['result_value'],
                'result_text'=>$r['result_text'],
                'disposition'=>self::disposition((string)($r['disposition']?:($r['overall_disposition']?:'PENDING'))),
                'overall_disposition'=>self::disposition((string)($r['overall_disposition']?:'PENDING')),
                'modified'=>$modified,
                'modified_display'=>$modified!==''?date('d/m/Y H:i',strtotime($modified)):'--',
                'created_by_name'=>(string)($r['created_by_name']??''),
                'created_by_badge'=>(string)($r['created_by_badge']??''),
                'modified_by_name'=>(string)($r['modified_by_name']??''),
                'raw_samples'=>$raw['raw_samples']??null,
                'samples'=>$raw['samples']??null,
                'carry_forward'=>$raw['carry_forward']??null,
            ];
        }
        if(!$saved)return;
        foreach($rows as &$row){
            $tankKey=((int)$row['product_id']).'|'.strtoupper((string)$row['tank']);
            if(array_key_exists($tankKey,$batches)){$row['batch']=$batches[$tankKey];$row['batch_saved']=true;}
            $bucket=[];
            foreach(($row['spec_options']??[]) as $option){
                $spec=strtoupper((string)($option['spec_type']??''));
                foreach(($option['rules']??[]) as $rule){
                    $key=$tankKey.'|'.$spec.'|'.((int)$rule['id']);
                    if(isset($saved[$key]))$bucket[$spec]=$saved[$key];
                }
            }
            if($bucket)$row['saved_by_spec']=$bucket;
        }
        unset($row);
    }

    /** Summary readers receive calculated values, without raw sample inputs. */
    public static function resultsOnly(array $rows): array {
        foreach($rows as &$row){
            foreach($row['saved_by_spec']??[] as $spec=>$saved){
                $torque=[];
                foreach($saved['raw_samples']??[] as $sample){
                    if(is_numeric($sample['B']??null))$torque[]=(float)$sample['B'];
                }
                $saved['torque_value']=$torque?array_sum($torque)/count($torque):null;
                unset($saved['raw_samples'],$saved['samples']);
                $row['saved_by_spec'][$spec]=$saved;
            }
        }
        unset($row);
        return $rows;
    }

    private static function norm(string $value): string {
        return preg_replace('/[^a-z0-9]+/','',strtolower($value));
    }
}
