<?php
require_once __DIR__.'/spec.php';
require_once __DIR__.'/Calculation.php';

/** Incoming form validation and authoritative calculation of optional raw readings. */
class IncomingForm {
    public static function dispositionChecks(array $checks): array {
        $checks=array_values(array_unique($checks));
        if(array_diff($checks,['PASS','FAIL','ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC']))throw new InvalidArgumentException('Tick a valid form disposition.');
        $status=array_values(array_intersect($checks,['PASS','FAIL']));$decision=array_values(array_intersect($checks,['ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC']));
        if(count($status)>1||count($decision)>1)throw new InvalidArgumentException('Choose one PASS/FAIL result and one acceptance decision.');
        return array_merge($status,$decision);
    }
    public static function validateDetails(array $data): void {
        if(trim((string)($data['batch_no']??''))==='')throw new InvalidArgumentException('Enter the batch number.');
        if(!in_array($data['packaging_condition']??'',['Good','Bad'],true))throw new InvalidArgumentException('Select the packaging condition.');
        foreach(['received_date','manufacturing_date','expiry_date'] as $key){
            $date=trim((string)($data[$key]??''));
            if($date===''&&$key!=='received_date')continue;
            $parsed=DateTime::createFromFormat('!Y-m-d',$date);
            if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Enter a valid '.str_replace('_',' ',$key).'.');
        }
        $inspection=str_replace('T',' ',trim((string)($data['inspection_at']??'')));
        $parsed=DateTime::createFromFormat('!Y-m-d H:i:s',$inspection);
        if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$inspection)throw new InvalidArgumentException('Enter a valid inspection date and time.');
        if(!empty($data['manufacturing_date'])&&!empty($data['expiry_date'])&&$data['expiry_date']<$data['manufacturing_date'])throw new InvalidArgumentException('Expiry date cannot be before manufacturing date.');
    }

    public static function results(array $profile,array $submitted): array {
        $rules=array_column($profile['rules'],null,'id');$seen=[];$results=[];
        foreach($submitted as $result){
            $id=(int)($result['rule_id']??0);$rule=$rules[$id]??null;
            if(!$rule||isset($seen[$id])||$rule['operator']==='INFO')throw new InvalidArgumentException('The test selection has changed. Reload the material specification.');
            $seen[$id]=true;
            unset($result['value']);
            $raw=$result['raw_samples']??null;
            if($raw!==null){
                if(!$rule['method_key']||!is_array($raw)||count($raw)>max(1,(int)$rule['sample_count']))throw new InvalidArgumentException('Invalid calculator samples.');
                $result['samples']=[];
                foreach($raw as $inputs){
                    if(!is_array($inputs))throw new InvalidArgumentException('Invalid calculator readings.');
                    if(!array_filter($inputs,fn($value)=>$value!==''&&$value!==null)){$result['samples'][]='';continue;}
                    $keys=self::calculationKeys((int)$rule['method_key']);
                    foreach($inputs as $value){self::numeric($value);}
                    if($keys&&count(array_filter($keys,fn($key)=>isset($inputs[$key])&&$inputs[$key]!==''))!==count($keys)){$result['samples'][]='';continue;}
                    $calculation=qc_calculate((int)$rule['method_key'],$inputs);
                    $result['samples'][]=$calculation['result'];
                }
                $result['calculator']=['method_key'=>(int)$rule['method_key']];
            }
            if(($rule['data_type']??'')==='NUMERIC'){
                $samples=$result['samples']??[];
                if(!is_array($samples)||count($samples)>max(1,(int)$rule['sample_count']))throw new InvalidArgumentException('Invalid number of test samples.');
                foreach($samples as $value){if($value!==''&&$value!==null&&(!is_numeric($value)||!is_finite((float)$value)))throw new InvalidArgumentException('Enter valid numeric readings.');}
            }
            $results[]=$result;
        }
        return $results;
    }

    private static function numeric($value) {
        if($value===''||$value===null)return '';
        if(!is_numeric($value)||!is_finite((float)$value))throw new InvalidArgumentException('Enter valid numeric measurements.');
        return (float)$value;
    }

    private static function calculationKeys(int $method): array {
        return [1=>['A','B','C'],7=>['A','B','C'],12=>['C','D'],14=>['A','B','C'],15=>['A','B','C'],16=>['A','C','D'],17=>['A','C','D']][$method]??[];
    }

    /** Printed sign-off names are form content, not an approval workflow. */
    public static function signoff(array $form,array $tester,array $verifiers): array {
        $signoff=$form['signoff']??[];
        if(!is_array($signoff))throw new InvalidArgumentException('Invalid form sign-off details.');
        $badge=trim((string)($signoff['verifier_badge']??''));
        $date=trim((string)($signoff['verification_date']??''));
        $verifierName='';
        if($badge!==''){
            foreach($verifiers as $verifier){
                if((string)$verifier['badge']===$badge){$verifierName=(string)$verifier['name'];break;}
            }
            if($verifierName==='')throw new InvalidArgumentException('Select a current verifier from the user list.');
        }
        if($date!==''){
            $parsed=DateTime::createFromFormat('!Y-m-d',$date);
            if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new InvalidArgumentException('Choose a valid verification date.');
        }
        return [
            'tester_badge'=>(string)$tester['badge'],
            'tester_name'=>(string)$tester['name'],
            'verifier_badge'=>$badge,
            'verifier_name'=>$verifierName,
            'verification_date'=>$date,
        ];
    }

    /** Document-specific measurements stay in existing result JSON; unspecified limits remain informational. */
    public static function measurements(array $profile,array $material,array $form): array {
        $type=strtoupper((string)($form['type']??$material['material_type']??''));
        if(!in_array($type,['LIQUID','POWDER'],true)||$type!==strtoupper((string)$material['material_type']))throw new InvalidArgumentException('Choose the form matching the material: Liquid or Powder.');
        $out=['type'=>$type,'packaging_remark'=>trim((string)($form['packaging_remark']??'')),'viscosity'=>[],'observations'=>[],'sample_labels'=>[],'particle_size'=>[],'sections'=>[]];
        $checks=$form['disposition_checks']??[];
        $allowed=['PASS','FAIL','ACCEPT','ACCEPT WITH LOC','REJECT WITH LOC'];
        if(!is_array($checks)||array_diff($checks,$allowed))throw new InvalidArgumentException('Tick a valid form disposition.');
        $out['disposition_checks']=self::dispositionChecks($checks);
        $viscosity=$form['viscosity']??[];
        $instrument=(string)($viscosity['instrument']??'');
        if(!in_array($instrument,['','LV','RV'],true))throw new InvalidArgumentException('Select LV or RV for the viscosity instrument.');
        $out['viscosity']=['setup'=>trim((string)($viscosity['setup']??'')),'instrument'=>$instrument,'spindle'=>self::numeric($viscosity['spindle']??''),'speed_rpm'=>self::numeric($viscosity['speed_rpm']??''),'time_minutes'=>self::numeric($viscosity['time_minutes']??''),'temperature_c'=>self::numeric($viscosity['temperature_c']??'')];
        foreach(['PH','VISC','SG'] as $code)$out['observations'][$code]=self::numeric($form['observations'][$code]??'');
        $out['observations']['APP']=trim((string)($form['observations']['APP']??''));
        foreach(['TSC','MOISTURE','WET_SIEVE','DRY_SIEVE','PURITY'] as $key){
            $out['sample_labels'][$key]=['S1','S2','S3'];
        }
        if($type==='LIQUID')foreach(['D10','D50','D90','mean'] as $key)$out['particle_size'][$key]=self::numeric($form['particle_size'][$key]??'');
        $rules=array_column($profile['rules'],null,'parameter_code');
        foreach(($form['sections']??[]) as $key=>$section){
            if(!in_array($key,['TSC','WET_SIEVE','MOISTURE','DRY_SIEVE'],true)&&!preg_match('/^PURITY(?:_\d+)?$/',$key))throw new InvalidArgumentException('Invalid measurement section.');
            if($type==='LIQUID'&&in_array($key,['MOISTURE','DRY_SIEVE'],true))throw new InvalidArgumentException('Moisture and dry sieve belong to the Powder form.');
            if(!is_array($section))throw new InvalidArgumentException('Invalid measurement data.');
            $enabled=!empty($section['enabled']);$mode=$section['mode']??'raw';
            if(!in_array($mode,['raw','direct'],true))throw new InvalidArgumentException('Invalid measurement input mode.');
            $method=['TSC'=>1,'WET_SIEVE'=>7,'MOISTURE'=>14,'DRY_SIEVE'=>15][$key]??(int)($rules[$key]['method_key']??0);
            if(!in_array($method,[1,7,12,14,15,16,17],true))$method=0;
            $inputs=$section['inputs']??[];$direct=$section['samples']??[];
            if(!is_array($inputs)||!is_array($direct)||count($inputs)>3||count($direct)>3)throw new InvalidArgumentException('Use up to three samples per section.');
            $clean=[];$values=[];$fine=[];
            for($i=0;$i<3;$i++){
                $sample=[];
                foreach(['A','B','C','N','V','W1','W2','W3'] as $field)$sample[$field]=self::numeric($inputs[$i][$field]??'');
                $clean[]=$sample;$value='';
                if($enabled){
                    if($mode==='direct'||!$method)$value=self::numeric($direct[$i]??'');
                    else{
                        $calc=$sample;
                        if($key==='WET_SIEVE')$calc=['A'=>$sample['W1'],'B'=>$sample['W2'],'C'=>$sample['W3']];
                        if(strpos($key,'PURITY')===0)$calc=['A'=>$sample['A'],'C'=>0,'D'=>$sample['V']];
                        $required=self::calculationKeys($method);
                        if(count(array_filter($required,fn($field)=>$calc[$field]!==''))===count($required))$value=self::numeric(qc_calculate($method,$calc)['result']);
                    }
                }
                $values[]=$value;if($key==='WET_SIEVE')$fine[]=$value===''?'':100-$value;
            }
            $out['sections'][$key]=['enabled'=>$enabled,'mode'=>$mode,'method_key'=>$method,'inputs'=>$clean,'samples'=>$values,'average'=>qc_average($values),'filter_size_um'=>self::numeric($section['filter_size_um']??'')];
            if($key==='WET_SIEVE')$out['sections'][$key]+=['particle_below_300_mesh'=>$fine,'particle_average'=>qc_average($fine)];
        }
        return $out;
    }

    public static function snapshot(array $profile,array $material): array {
        return [
            'source_ref'=>$profile['source_ref']??'', 'revision'=>$profile['revision']??'',
            'supplier'=>$material['supplier']??'', 'chemical_type'=>$material['chemical_type']??'',
            'material_type'=>$material['material_type']??'', 'shelf_life_text'=>$material['shelf_life_text']??'',
            'rules'=>array_map(fn($r)=>array_intersect_key($r,array_flip(['id','parameter_code','parameter_name','operator','low_value','high_value','target_text','source_display','unit'])), $profile['rules']),
        ];
    }
}
