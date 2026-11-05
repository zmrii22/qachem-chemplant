<?php
require_once __DIR__.'/TrackingStorage.php';
require_once __DIR__.'/spec.php';
require_once __DIR__.'/ReferenceStore.php';

class Tracking {
    public static function reference(): array {
        $reference=json_decode(file_get_contents(__DIR__.'/../data/tracking_reference.json'),true,512,JSON_THROW_ON_ERROR);
        require_once __DIR__.'/ReferenceStore.php';
        if(ReferenceStore::ready(db_connect()))$reference['remarks']=ReferenceStore::remarks(db_connect());
        return $reference;
    }
    public static function date(string $date,bool $optional=false): string {
        if($optional&&$date==='')return '';
        $parsed=DateTime::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid calendar date.');
        return $date;
    }
    public static function standard(array $rule): string {
        $display=$rule;
        foreach(['low_value','high_value'] as $key)if(isset($display[$key])&&is_numeric($display[$key]))$display[$key]=number_format((float)$display[$key],2,'.','');
        return rule_display($display);
    }
    /** Specification choices belong to the linked product, independently of destination. */
    public static function coaSpecifications(PDO $pdo,int $productId,string $factory=''): array {
        $product=qc_master_product_by_id($productId);if(!$product)throw new InvalidArgumentException('Product is unavailable.');
        $options=[];
        foreach(qc_master_active('spec_profiles') as $profile){
            if(($profile['entity_type']??'')!=='PRODUCT'||(int)$profile['entity_id']!==$productId||strtoupper($profile['spec_type'])!=='QS')continue;
            $scope=trim((string)($profile['factory_scope']??''))?:'ALL';
            $options[]=['id'=>(int)$profile['id'],'product_name'=>$product['product_name'],'factory_scope'=>$scope,'label'=>$product['product_name'].' · '.$scope];
        }
        usort($options,fn($a,$b)=>strnatcasecmp($a['factory_scope'],$b['factory_scope'])?:($a['id']<=>$b['id']));
        return $options;
    }
    public static function defaultCoaSpecification(PDO $pdo,array $source): ?int {
        $options=self::coaSpecifications($pdo,(int)$source['product_id']);
        $scope=trim((string)($source['factory_specification']??'ALL'))?:'ALL';
        foreach([$scope,'ALL'] as $match)foreach($options as $option)if(strcasecmp($option['factory_scope'],$match)===0)return $option['id'];
        return $options[0]['id']??null;
    }
    public static function coaSpecificationId($value): int {
        if($value===null||$value===''||$value===0||$value==='0')return 0;
        $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if($id===false)throw new InvalidArgumentException('Choose a valid COA specification.');
        return $id;
    }
    public static function coaSpecification(PDO $pdo,int $productId,string $factory,int $id=0): ?array {
        if(!$id)return get_spec_profile($pdo,'PRODUCT',$productId,'QS',$factory);
        foreach(self::coaSpecifications($pdo,$productId,$factory) as $option)if($option['id']===$id)return get_spec_profile_by_id($pdo,$id);
        throw new InvalidArgumentException('Choose an active COA specification for this product.');
    }
    /** Shared list/export query. A null limit exports every record, using live Inprocess values. */
    public static function records(PDO $pdo,array $filters=[],?int $limit=500): Generator {
        $where=[];$args=[];
        foreach(['from'=>'>=','to'=>'<='] as $key=>$operator)if(!empty($filters[$key])){
            $where[]='t.manufacturing_date '.$operator.' ?';$args[]=self::date((string)$filters[$key]);
        }
        if(!empty($filters['plant'])){$where[]='COALESCE(b.plant,i.plant)=?';$args[]=$filters['plant'];}
        if(!empty($filters['q'])){
            $search=trim((string)$filters['q']);$matchingProducts=[];
            foreach(qc_master_products() as $product)if(qc_text_contains($product['product_name'],$search))$matchingProducts[]=(int)$product['id'];
            $productMatch=$matchingProducts?' OR COALESCE(b.product_id,i.entity_id) IN ('.implode(',',array_fill(0,count($matchingProducts),'?')).')':'';
            $where[]="(CONCAT_WS(' ',b.batch_no,i.batch_no,t.planned_batch_no,t.factory,t.po_no) LIKE ?".$productMatch.')';
            $args[]='%'.$search.'%';array_push($args,...$matchingProducts);
        }
        $sql='SELECT t.* FROM t_qc_tracking t LEFT JOIN t_qc_inprocess_batch b ON b.id=t.source_batch_id LEFT JOIN t_qc_inspection i ON i.id=t.source_inspection_id';
        $sql.=($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY t.manufacturing_date DESC,t.id DESC';
        if($limit!==null)$sql.=' LIMIT '.max(1,$limit);
        $query=$pdo->prepare($sql);$query->execute($args);
        while($row=$query->fetch())yield self::withLiveSource($pdo,$row);
    }
    public static function sources(PDO $pdo): array {
        qc_tracking_storage($pdo);
        $metadata=$pdo->query('SELECT updated_at AS saved_at,run_no,parent_batch_id,root_batch_id,preset_json,id AS source_batch_id,product_id,plant,inspection_at,tank,batch_no,created_by_badge,modified_by_name AS author FROM t_qc_inprocess_batch ORDER BY inspection_at DESC,id DESC LIMIT 1000')->fetchAll();
        $legacy=$pdo->query("SELECT id AS source_inspection_id,spec_profile_id AS source_profile_id,entity_id AS product_id,plant,inspection_at,sampling_location AS tank,batch_no,created_by_badge,created_by_name AS author FROM t_qc_inspection WHERE inspection_type='INPROCESS' AND status<>'CANCELLED' AND inprocess_batch_id IS NULL AND NULLIF(TRIM(batch_no),'') IS NOT NULL ORDER BY inspection_at DESC,id DESC LIMIT 2000")->fetchAll();
        $sources=[];$productRemarks=[];
        foreach(array_merge($metadata,$legacy) as $row){
            $productId=(int)$row['product_id'];
            if(!isset($productRemarks[$productId]))$productRemarks[$productId]=self::productRemarks($pdo,$productId);
            $source=self::presentSource($row,$productRemarks[$productId]);if(!$source)continue;
            $contextKey=hash('sha256',implode('|',[$row['product_id'],$row['plant'],$row['inspection_at'],$row['tank'],$row['created_by_badge'],$row['run_no']??1]));
            if(isset($sources[$contextKey]))continue;
            $sources[$contextKey]=$source;
        }
        $sources=array_values($sources);usort($sources,fn($a,$b)=>strcmp($b['inspection_at'],$a['inspection_at'])?:strnatcasecmp($a['batch_no'],$b['batch_no']));
        return $sources;
    }
    private static function presentSource(array $row,array $remarks): ?array {
        $product=qc_master_product_by_id((int)$row['product_id']);if(!$product)return null;
        $key=!empty($row['source_batch_id'])?'batch:'.$row['source_batch_id']:'inspection:'.$row['source_inspection_id'];
        $preset=json_decode($row['preset_json']??'{}',true)?:[];
        $profile=!empty($row['source_profile_id'])?qc_master_profile_by_id((int)$row['source_profile_id']):null;
        $row['factory_specification']=$preset['factory_specification']??$profile['factory_scope']??'ALL';
        $type=$preset['sample_type']??'NEW_SAMPLE';
        $runNo=(int)($row['run_no']??1);$row['run_label']=$runNo===1?'':(['RETEST'=>'Retest','REWORK'=>'Rework','NEW_SAMPLE'=>'New Sample'][$type]??'New Sample');unset($row['preset_json']);
        return $row+['key'=>$key,'product_name'=>$product['product_name'],'shelf_life_value'=>$product['shelf_life_value']??null,'shelf_life_unit'=>$product['shelf_life_unit']??'MONTH','remark_keys'=>array_column($remarks,'key'),'coa_remarks'=>$remarks];
    }
    public static function source(PDO $pdo,string $key): array {
        if(preg_match('/^(batch|inspection):([1-9][0-9]*)$/D',$key,$match)){
            $sql=$match[1]==='batch'
                ?'SELECT updated_at AS saved_at,run_no,parent_batch_id,root_batch_id,preset_json,id AS source_batch_id,product_id,plant,inspection_at,tank,batch_no,created_by_badge,modified_by_name AS author FROM t_qc_inprocess_batch WHERE id=?'
                :"SELECT id AS source_inspection_id,spec_profile_id AS source_profile_id,entity_id AS product_id,plant,inspection_at,sampling_location AS tank,batch_no,created_by_badge,created_by_name AS author FROM t_qc_inspection WHERE id=? AND inspection_type='INPROCESS' AND status<>'CANCELLED'";
            $query=$pdo->prepare($sql);$query->execute([$match[2]]);$row=$query->fetch();
            if($row&&($source=self::presentSource($row,self::productRemarks($pdo,(int)$row['product_id']))))return $source;
        }else{
            // Accept selectors from browser tabs opened before stable source IDs were introduced.
            foreach(self::sources($pdo) as $source){
                $context=hash('sha256',implode('|',[$source['product_id'],$source['plant'],$source['inspection_at'],$source['tank'],$source['created_by_badge']]));
                if(hash_equals(hash('sha256',$context.'|'.$source['batch_no']),$key))return $source;
            }
        }
        throw new InvalidArgumentException('Select a saved Inprocess batch.');
    }
    public static function withLiveSource(PDO $pdo,array $record): array {
        $details=$record['details']??json_decode($record['details_json'],true)?:[];
        unset($details['source_key'],$details['expiry_date'],$details['remark_keys'],$details['coa_remark_preset']);
        $key=!empty($record['source_batch_id'])?'batch:'.$record['source_batch_id']:(!empty($record['source_inspection_id'])?'inspection:'.$record['source_inspection_id']:'');
        $record['planned']=$key==='';
        try{if($key==='')throw new InvalidArgumentException('Awaiting Inprocess batch.');$source=self::source($pdo,$key);}
        catch(InvalidArgumentException $error){
            $record['source_missing']=true;$record['details']=$details;$record['coa_remarks']=[];
            foreach(['plant','product_id','chemical_name','batch_no','source_inspection_at','source_tank','source_badge','testing_date'] as $field)$record[$field]=null;
            if($record['planned'])$record['batch_no']=$record['planned_batch_no']??'';
            return $record;
        }
        return array_replace($record,['plant'=>$source['plant'],'product_id'=>$source['product_id'],'chemical_name'=>$source['product_name'],'batch_no'=>$source['batch_no'],'source_inspection_at'=>$source['inspection_at'],'source_tank'=>$source['tank'],'source_badge'=>$source['created_by_badge'],'testing_date'=>substr($source['inspection_at'],0,10),'source_missing'=>false,'coa_remarks'=>$source['coa_remarks'],'run_label'=>$source['run_label'],'source_saved_at'=>$source['saved_at']??null,'details'=>$details+['source_key'=>$source['key']]]);
    }
    public static function get(PDO $pdo,int $id,bool $lock=false): array {
        $query=$pdo->prepare('SELECT * FROM t_qc_tracking WHERE id=?'.($lock?' FOR UPDATE':''));$query->execute([$id]);$row=$query->fetch();
        if(!$row)throw new InvalidArgumentException('Tracking record not found.');
        return self::withLiveSource($pdo,$row);
    }
    public static function defaults(array $source,array $product): array {
        $keys=['base_compliance','base_returns'];$code=strtoupper($product['batch_code']??'');
        if(in_array($code,['INS','TS','MP'],true))$keys=array_merge($keys,['arp_storage','arp_ph']);
        elseif($code==='SH')$keys[]='hypo';
        elseif($code==='PTC')$keys[]='ptc';
        elseif(stripos($product['product_type']??'','cleaning')!==false&&$code!=='KZ')$keys[]='cleaning_chlorine';
        if(!empty($product['id'])&&ReferenceStore::ready(db_connect()))$keys=ReferenceStore::productRemarkKeys(db_connect(),(int)$product['id']);
        return ['source_key'=>$source['key'],'signatory'=>'','inspector'=>'','remark_keys'=>$keys];
    }
    /** Product selections are live reference data, never a Tracking override. */
    public static function productRemarks(PDO $pdo,int $productId): array {
        if(ReferenceStore::ready($pdo)){
            $keys=ReferenceStore::productRemarkKeys($pdo,$productId);
            $remarks=ReferenceStore::remarks($pdo);
        }else{
            $product=qc_master_product_by_id($productId);if(!$product)return [];
            $keys=self::defaults(['key'=>''],$product)['remark_keys'];$remarks=self::reference()['remarks'];
        }
        return array_values(array_filter($remarks,fn($remark)=>in_array($remark['key'],$keys,true)));
    }
    public static function expiry(array $product,string $manufacturing): string {
        $value=(float)($product['shelf_life_value']??0);if(!$value)return '';
        $date=new DateTimeImmutable($manufacturing);$unit=strtoupper((string)($product['shelf_life_unit']??'MONTH'));
        if(in_array($unit,['MONTH','MONTHS','YEAR','YEARS'],true)){
            $months=(int)round($value*(str_starts_with($unit,'YEAR')?12:1));
            $target=$date->modify('first day of this month')->modify('+'.$months.' months');
            return $target->setDate((int)$target->format('Y'),(int)$target->format('m'),min((int)$date->format('d'),(int)$target->format('t')))->format('Y-m-d');
        }
        return $date->modify('+'.(int)round($value*(str_starts_with($unit,'WEEK')?7:1)).' days')->format('Y-m-d');
    }
    public static function automaticDates(array $product,string $manufacturing,string $sourceAt): array {
        return ['testing_date'=>self::date(substr($sourceAt,0,10)),
            'expiry_date'=>self::expiry($product,self::date($manufacturing))];
    }
    public static function appearanceResult(array $rule): array {
        $text=trim(self::standard($rule));
        return ['text'=>$text,'disposition'=>$text===''?'PENDING':'RECORDED'];
    }
    public static function requiredForCoa(array $record): array {
        $details=$record['details']??json_decode($record['details_json']??'{}',true)?:[];$missing=[];
        foreach(['batch_no'=>'Batch','manufacturing_date'=>'Manufacturing Date','factory'=>'Factory','po_no'=>'Customer PO No.','packaging_no'=>'Tote Tank / Drum No.'] as $key=>$label){
            if(trim((string)($record[$key]??''))==='')$missing[]=$label;
        }
        if(trim((string)($details['inspector']??''))==='')$missing[]='Inspected by';
        if(trim((string)($details['signatory']??''))==='')$missing[]='COA signatory';
        return $missing;
    }
    public static function document(PDO $pdo,array $record): array {
        if(!empty($record['source_missing']))throw new InvalidArgumentException(!empty($record['planned'])?'Link an Inprocess run to generate a COA.':'Inprocess run unavailable.');
        $product=qc_master_product_by_id((int)$record['product_id']);if(!$product)throw new InvalidArgumentException('Product is unavailable.');
        $reference=self::reference();$details=$record['details']??json_decode($record['details_json'],true)?:[];
        $applicableRemarks=self::productRemarks($pdo,(int)$product['id']);
        $factories=array_column($reference['factories'],'name','code');
        if(!isset($factories[$record['factory']]))throw new InvalidArgumentException('Choose a factory from the reference list.');
        $coaSpecId=self::coaSpecificationId($details['coa_spec_profile_id']??null);
        $qs=self::coaSpecification($pdo,(int)$record['product_id'],$record['factory'],$coaSpecId);
        $runFilter=" AND NOT EXISTS (SELECT 1 FROM t_qc_inprocess_batch child WHERE child.id=i.inprocess_batch_id AND child.parent_batch_id IS NOT NULL)";
        $args=[$record['product_id'],$record['plant'],$record['source_inspection_at'],$record['source_tank'],$record['batch_no']];
        if(!empty($record['source_batch_id'])){
            $batch=$pdo->prepare('SELECT parent_batch_id FROM t_qc_inprocess_batch WHERE id=?');$batch->execute([$record['source_batch_id']]);
            if($batch->fetchColumn()){$runFilter=' AND i.inprocess_batch_id=?';$args[]=$record['source_batch_id'];}
        }
        $query=$pdo->prepare("SELECT i.id AS inspection_id,i.updated_at,i.inspection_at,i.status,r.* FROM t_qc_inspection i JOIN t_qc_result r ON r.inspection_id=i.id WHERE i.inspection_type='INPROCESS' AND i.status<>'CANCELLED' AND i.entity_id=? AND i.plant=? AND i.inspection_at=? AND i.sampling_location=? AND BINARY i.batch_no=BINARY ?".$runFilter." ORDER BY i.updated_at DESC,i.id DESC,r.attempt_no DESC,r.id DESC");
        $query->execute($args);
        $by=[];foreach($query->fetchAll() as $result){$code=strtoupper($result['parameter_code']);if(!isset($by[$code]))$by[$code]=$result;}
        $rows=[];$ids=[];$missing=self::requiredForCoa($record);$problems=$missing?['Fill: '.implode(', ',$missing).'.']:[];
        if(!$applicableRemarks)$problems[]='Set COA remarks for this product in Product Specification.';
        if(!$qs)$problems[]='No QS specification matches the product and destination factory.';
        $qsRules=array_column($qs['rules']??[],null,'parameter_code');
        // An explicitly selected specification also controls pH's IQS fallback scope.
        $iqsPh=null;
        if(!isset($qsRules['PH'])){
            $iqs=null;
            if($coaSpecId){
                foreach([$qs['factory_scope']??'ALL','ALL'] as $scope){
                    foreach(qc_master_active('spec_profiles') as $profile){
                        if(($profile['entity_type']??'')==='PRODUCT'&&(int)$profile['entity_id']===(int)$record['product_id']&&strtoupper($profile['spec_type'])==='IQS'&&strcasecmp(trim((string)($profile['factory_scope']??''))?:'ALL',$scope)===0){$iqs=get_spec_profile_by_id($pdo,(int)$profile['id']);break 2;}
                    }
                }
            }
            if(!$iqs)$iqs=get_spec_profile($pdo,'PRODUCT',(int)$record['product_id'],'IQS',$coaSpecId?$record['plant']:$record['factory']);
            $iqsPh=array_column($iqs['rules']??[],null,'parameter_code')['PH']??null;
        }
        $appearance=$qsRules['APP']??null;
        if($appearance){$app=self::appearanceResult($appearance);$rows[]=['name'=>'Appearance','code'=>'APP','result'=>$app['text'],'specification'=>self::standard($appearance),'unit'=>'','disposition'=>$app['disposition'],'inspection_id'=>null];if($app['disposition']==='PENDING')$problems[]='Appearance: PENDING';}
        $allRules=array_column(qc_master_data()['spec_rules'],null,'id');
        // Torque is entered with viscosity and appears as its own Inprocess result column.
        $viscosity=$by['VISC']??$by['VCP']??null;
        if($viscosity&&!isset($by['VTQ'])){
            $raw=json_decode($viscosity['raw_input_json']??'',true)?:[];$torque=[];
            foreach($raw['raw_samples']??[] as $sample)if(is_numeric($sample['B']??null))$torque[]=(float)$sample['B'];
            if($torque)$by['VTQ']=array_replace($viscosity,['parameter_code'=>'VTQ','parameter_name'=>'Viscosity (Torque%)','result_value'=>array_sum($torque)/count($torque),'result_text'=>null,'unit'=>'%','raw_input_json'=>json_encode(['samples'=>$torque])]);
        }
        $order=array_flip(array_keys(ReferenceStore::parameters()));
        uksort($by,fn($a,$b)=>($order[$a]??100)<=>($order[$b]??100));
        foreach($by as $code=>$result){
            if($code==='APP')continue;
            $sourceRule=$allRules[$result['spec_rule_id']]??[];$rule=$qsRules[$code]??null;
            $specType=$rule?'QS':null;
            if($code==='PH'&&!$rule&&$iqsPh){$rule=$iqsPh;$specType='IQS';}
            $value=$result['result_value'];$text=$result['result_text'];$ids[]=(int)$result['inspection_id'];
            $hasValue=$value!==null||trim((string)$text)!=='';
            $controlled=$rule&&!in_array($rule['operator'],['INFO','OBSERVE'],true);
            $status=$controlled?evaluate_rule($rule,$value,$text):($hasValue?'RECORDED':'PENDING');
            if(!in_array($status,['PASS','RECORDED'],true))$problems[]=$result['parameter_name'].': '.$status;
            if(($sourceRule['data_type']??'NUMERIC')==='NUMERIC'){
                $raw=json_decode($result['raw_input_json']??'',true)?:[];
                $samples=array_filter($raw['samples']??[],fn($sample)=>$sample!==null&&$sample!==''&&is_numeric($sample));
                $count=max(1,(int)($sourceRule['sample_count']??1));
                if(count($samples)<$count)$problems[]='Complete '.$count.' sample readings for '.$result['parameter_name'].'.';
            }
            $display=$value!==null?number_format((float)$value,2,'.',''):($text?:'—');
            $rows[]=['name'=>$result['parameter_name'],'code'=>$code,'result'=>$display,'specification'=>$rule?($specType==='IQS'?'IQS: ':'').self::standard($rule):'Not specified in QS','spec_type'=>$specType,'unit'=>$result['unit']??'','disposition'=>$status,'inspection_id'=>$result['inspection_id']];
        }
        $remarks=array_column($applicableRemarks,'text');
        $signatory=(string)($details['signatory']??'');
        if($signatory!==''&&!in_array($signatory,$reference['verifiers'],true))$problems[]='Choose a valid COA signatory.';
        if(!$ids)$problems[]='No measured Inprocess results are saved for this batch and sampling context.';
        if(strcasecmp($record['batch_no'],$product['batch_code'])===0)$problems[]='Complete the batch number after the product prefix in Inprocess.';
        $dates=self::automaticDates($product,$record['manufacturing_date'],$record['source_inspection_at']);
        if($dates['expiry_date']==='')$problems[]='Set the product shelf life for Expiry Date.';
        return ['plant'=>$record['plant'],'customer_name'=>$factories[$record['factory']],'factory'=>$record['factory'],'product'=>$product['product_name'],'batch'=>$record['batch_no'],'packaging_no'=>$record['packaging_no'],'po_no'=>$record['po_no'],'manufacturing_date'=>$record['manufacturing_date'],'expiry_date'=>$dates['expiry_date'],'testing_date'=>$dates['testing_date'],'shelf_life_value'=>$product['shelf_life_value']??null,'shelf_life_unit'=>$product['shelf_life_unit']??'MONTH','source_at'=>$record['source_inspection_at'],'source_tank'=>$record['source_tank'],'results'=>$rows,'remarks'=>$remarks,'coa_remarks'=>$applicableRemarks,'signatory'=>$signatory,'qs_profile_id'=>$qs['id']??null,'coa_spec_profile_id'=>$coaSpecId?:null,'coa_spec_label'=>$qs?$product['product_name'].' · '.(trim((string)($qs['factory_scope']??''))?:'ALL'):'','source_ids'=>array_values(array_unique($ids)),'problems'=>array_values(array_unique($problems))];
    }
    public static function save(PDO $pdo,array $data,array $user): int {
        $old=!empty($data['id'])?self::get($pdo,(int)$data['id'],true):null;
        $key=trim((string)($data['source_key']??''));
        $source=$key!==''?self::source($pdo,$key):null;$reference=self::reference();
        $batch=trim((string)($data['batch_no']??$source['batch_no']??''));
        if($batch===''||qc_text_length($batch)>100)throw new InvalidArgumentException('Enter a batch number of up to 100 characters.');
        if($source&&$batch!==$source['batch_no'])throw new InvalidArgumentException('Choose the Inprocess run matching the entered batch number.');
        $factory=trim((string)($data['factory']??''));
        if(($source||$factory!=='')&&!in_array($factory,array_column($reference['factories'],'code'),true))throw new InvalidArgumentException('Choose a destination factory.');
        $manufacturing=self::date((string)($data['manufacturing_date']??''));
        $inspector=trim((string)($old['details']['inspector']??''))?:$user['name'];
        $details=['signatory'=>trim((string)($data['signatory']??'')),'inspector'=>$inspector];
        $coaSpecId=self::coaSpecificationId(array_key_exists('coa_spec_profile_id',$data)?$data['coa_spec_profile_id']:($old['details']['coa_spec_profile_id']??null));
        if($coaSpecId){
            if(!$source)throw new InvalidArgumentException('Link an Inprocess run before choosing its COA specification.');
            self::coaSpecification($pdo,(int)$source['product_id'],$factory,$coaSpecId);
            $details['coa_spec_profile_id']=$coaSpecId;
        }
        if($details['signatory']!==''&&!in_array($details['signatory'],$reference['verifiers'],true))throw new InvalidArgumentException('Select a signatory from the workbook list.');
        $po=trim((string)($data['po_no']??'N/A'));$packaging=trim((string)($data['packaging_no']??''));
        if($po===''&&!$source)$po='N/A';
        if($po==='')throw new InvalidArgumentException('Enter the Customer PO No.');
        if(qc_text_length($po)>100||qc_text_length($packaging)>255)throw new InvalidArgumentException('PO or packaging number is too long.');
        $remark=trim((string)($data['remark']??''));
        $status=$source?'SAVED':'DRAFT';$generated=null;$upload=null;
        if($old&&!empty($old['coa_generated_at'])){
            $oldDetails=$old['details'];unset($oldDetails['source_key'],$oldDetails['expiry_date'],$oldDetails['appearance'],$oldDetails['tracking_remark'],$oldDetails['custom_remark'],$oldDetails['remark_keys'],$oldDetails['coa_remark_preset']);
            $unchanged=($old['source_batch_id']??null)==($source['source_batch_id']??null)
                &&($old['source_inspection_id']??null)==($source['source_inspection_id']??null)
                &&$old['manufacturing_date']===$manufacturing&&$old['factory']===$factory
                &&$old['po_no']===$po&&$old['packaging_no']===$packaging&&($old['remark']??'')===$remark&&$oldDetails==$details;
            if($unchanged){$status=$old['status'];$generated=$old['coa_generated_at'];$upload=$old['upload_date'];}
        }
        $values=[$source['source_batch_id']??null,$source['source_inspection_id']??null,$source?null:$batch,$manufacturing,$factory,$po,$packaging,$remark,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$status,$generated,$upload,$user['badge'],$user['name']];
        if($old){
            $query=$pdo->prepare("UPDATE t_qc_tracking SET source_batch_id=?,source_inspection_id=?,planned_batch_no=?,manufacturing_date=?,factory=?,po_no=?,packaging_no=?,remark=?,details_json=?,status=?,coa_generated_at=?,upload_date=?,modified_by_badge=?,modified_by_name=?,plant=NULL,product_id=NULL,chemical_name=NULL,batch_no=NULL,source_inspection_at=NULL,source_tank=NULL,source_badge=NULL,testing_date=NULL,updated_at=NOW() WHERE id=?");
            $query->execute([...$values,$old['id']]);return (int)$old['id'];
        }
        $number='TR-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));
        $query=$pdo->prepare('INSERT INTO t_qc_tracking (source_batch_id,source_inspection_id,planned_batch_no,manufacturing_date,factory,po_no,packaging_no,remark,details_json,status,coa_generated_at,upload_date,modified_by_badge,modified_by_name,tracking_no,created_by_badge,created_by_name) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $query->execute([...$values,$number,$user['badge'],$user['name']]);return (int)$pdo->lastInsertId();
    }

    public static function uploadDate(string $value,?string $existing=null): ?string {
        if($value==='')return null;
        self::date($value);
        if($value!==$existing&&$value<date('Y-m-d'))throw new InvalidArgumentException('Upload Date must be today or later.');
        return $value;
    }
    public static function saveUploadDate(PDO $pdo,array $record,string $value,array $user): void {
        if(empty($record['coa_generated_at'])){
            if($value==='')return;
            throw new InvalidArgumentException('Generate the COA before entering its Upload Date.');
        }
        $date=self::uploadDate(trim($value),$record['upload_date']??null);
        $query=$pdo->prepare('UPDATE t_qc_tracking SET upload_date=?,modified_by_badge=?,modified_by_name=?,updated_at=NOW() WHERE id=?');
        $query->execute([$date,$user['badge'],$user['name'],$record['id']]);
    }
}
